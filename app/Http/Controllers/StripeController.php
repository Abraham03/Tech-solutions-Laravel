<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\StripeService;
use App\Services\PaymentService;
use App\Services\InfrastructureService;
use App\Traits\ApiResponseTrait;
use App\Enums\PaymentTypeEnum;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Notifications\PaymentReceivedNotification;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripeController extends Controller
{
    use ApiResponseTrait;

    protected $stripeService;
    protected $paymentService;
    protected $infrastructureService;

    public function __construct(
        StripeService $stripeService,
        PaymentService $paymentService,
        InfrastructureService $infrastructureService
    ) {
        $this->stripeService = $stripeService;
        $this->paymentService = $paymentService;
        $this->infrastructureService = $infrastructureService;
    }

    /**
     * Genera el link de cobro para el cliente.
     */
    public function createSession(Request $request)
    {
        $session = $this->stripeService->createCheckoutSession($request->all());
        return $this->successResponse(['url' => $session->url], 'Sesión de pago creada.');
    }

    /**
     * Recibe la notificación automática de Stripe.
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $endpoint_secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Webhook Stripe con firma inválida: ' . $e->getMessage());
            return response()->json(['error' => 'Firma inválida'], 400);
        }

        Log::info("Webhook Stripe recibido: {$event->type} ({$event->id})");

        // Si el pago fue exitoso en Stripe
        if ($event->type === 'checkout.session.completed') {
            return $this->processCompletedSession($event);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Registra el pago de una sesión de Checkout liquidada.
     *
     * IMPORTANTE: solo devolvemos 5xx cuando un reintento de Stripe puede ayudar
     * (fallos transitorios). Un rechazo por regla de negocio se registra en el log
     * y responde 200, porque reintentarlo daría siempre el mismo resultado.
     */
    private function processCompletedSession($event)
    {
        $session = $event->data->object;

        // La sesión puede completarse sin que el dinero esté cobrado
        // (payment_method_collection: if_required, pagos asíncronos, etc.)
        if ($session->payment_status !== 'paid') {
            Log::info("Sesión {$session->id} completada sin pago liquidado (payment_status: {$session->payment_status}). Se ignora.");
            return response()->json(['status' => 'ignored']);
        }

        // Idempotencia: Stripe reenvía eventos y no queremos duplicar ni chocar
        // contra el índice unique de stripe_payment_intent_id.
        if ($session->payment_intent && Payment::where('stripe_payment_intent_id', $session->payment_intent)->exists()) {
            Log::info("Pago {$session->payment_intent} ya registrado. Evento {$event->id} ignorado.");
            return response()->json(['status' => 'already_processed']);
        }

        // Stripe omite las claves de metadata con valor null, así que leemos a la defensiva.
        $metadata    = $session->metadata ?? [];
        $clientId    = $metadata['client_id'] ?? null;
        $projectId   = $metadata['project_id'] ?? null;
        $serviceId   = $metadata['service_id'] ?? null;
        $paymentType = $metadata['payment_type'] ?? null;
        $amount      = $session->amount_total / 100;

        if (!$clientId || !$paymentType) {
            Log::error("Webhook {$event->id}: metadata incompleta en la sesión {$session->id}. No se puede registrar el pago.");
            return response()->json(['status' => 'invalid_metadata']);
        }

        try {
            // 1. Registramos el pago en nuestro cerebro financiero
            $payment = $this->paymentService->createPayment([
                'client_id' => $clientId,
                'project_id' => $projectId,
                'service_id' => $serviceId,
                'amount' => $amount,
                'payment_method' => 'stripe',
                'payment_type' => $paymentType,
                'status' => 'completed',
                'stripe_payment_intent_id' => $session->payment_intent,
                'paid_at' => now(),
            ]);
        } catch (ValidationException $e) {
            // Regla de negocio rechazada: reintentar no sirve de nada.
            Log::error("Webhook {$event->id}: pago de \${$amount} rechazado por regla de negocio en la sesión {$session->id}. " . $e->getMessage());
            return response()->json(['status' => 'rejected']);
        } catch (\Exception $e) {
            // Fallo potencialmente transitorio (BD caída): dejamos que Stripe reintente.
            Log::error("Webhook {$event->id}: error registrando el pago de la sesión {$session->id}: " . $e->getMessage());
            return response()->json(['error' => 'Error interno procesando pago.'], 500);
        }

        // 2. Si fue una renovación, extendemos la vigencia del servicio.
        // Aislado: si falla, el pago ya quedó guardado y no queremos que Stripe reintente.
        if ($paymentType === PaymentTypeEnum::RENEWAL->value && $serviceId) {
            try {
                $service = Service::find($serviceId);
                if ($service) {
                    $this->infrastructureService->renewService($service);
                }
            } catch (\Exception $e) {
                Log::error("Webhook {$event->id}: pago {$payment->id} guardado, pero falló la renovación del servicio {$serviceId}: " . $e->getMessage());
            }
        }

        // 3. Dejamos rastro en el historial de notificaciones
        try {
            NotificationLog::create([
                'client_id' => $clientId,
                'service_id' => $serviceId,
                'type' => 'push_alert',
                'message_body' => "Pago de {$amount} MXN recibido vía Stripe.",
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning("Webhook {$event->id}: no se pudo guardar el NotificationLog: " . $e->getMessage());
        }

        // 4. Te avisamos por push (Usuario ID 1 - Administrador).
        // Si Firebase falla, solo lo anotamos: a Stripe le decimos que todo salió bien.
        try {
            $admin = User::find(1);
            if ($admin) {
                $admin->notify(new PaymentReceivedNotification($payment));
            }
        } catch (\Exception $pushError) {
            Log::warning('Fallo Push de Firebase: ' . $pushError->getMessage());
        }

        return response()->json(['status' => 'success']);
    }
}
