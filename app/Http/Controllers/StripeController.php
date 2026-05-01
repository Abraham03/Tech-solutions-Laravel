<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\StripeService;
use App\Services\PaymentService;
use App\Traits\ApiResponseTrait;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Models\NotificationLog;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripeController extends Controller
{
    use ApiResponseTrait;

    protected $stripeService;
    protected $paymentService;

    public function __construct(StripeService $stripeService, PaymentService $paymentService)
    {
        $this->stripeService = $stripeService;
        $this->paymentService = $paymentService;
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
            return response()->json(['error' => 'Firma inválida'], 400);
        }

        // Si el pago fue exitoso en Stripe
        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            
            try {
                // 1. Intentamos registrar el pago en nuestro cerebro financiero
                $payment = $this->paymentService->createPayment([
                    'client_id' => $session->metadata->client_id,
                    'project_id' => $session->metadata->project_id,
                    'service_id' => $session->metadata->service_id,
                    'amount' => $session->amount_total / 100,
                    'payment_method' => 'stripe',
                    'payment_type' => $session->metadata->payment_type,
                    'status' => 'completed',
                    'stripe_payment_intent_id' => $session->payment_intent,
                    'paid_at' => now(),
                ]);

                // 2. Si se guardó con éxito, te notificamos a ti (Usuario ID 1 - Administrador)
                $admin = \App\Models\User::find(1);
                if ($admin) {
                    $admin->notify(new \App\Notifications\PaymentReceivedNotification($payment));
                }

                // ======= 3. NUEVO: GUARDAR EN LA BASE DE DATOS =======
                    NotificationLog::create([
                        'client_id' => $session->metadata->client_id,
                        'service_id' => $session->metadata->service_id,
                        'type' => 'push_alert',
                        // <-- SOLUCIÓN: Separamos el texto de la matemática
                        'message_body' => "Pago de " . ($session->amount_total / 100) . " MXN recibido vía Stripe.",
                        'status' => 'sent',
                        'sent_at' => now()
                    ]);

            } catch (\Illuminate\Validation\ValidationException $e) {
                // Si la regla matemática de saldos lo rechaza
                \Illuminate\Support\Facades\Log::warning('Webhook rechazado por saldo: ' . $e->getMessage());
                return response()->json(['error' => $e->getMessage()], 422);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Error en Webhook: ' . $e->getMessage());
                return response()->json(['error' => 'Error interno procesando pago.'], 500);
            }
        }

        return response()->json(['status' => 'success']);
    }
}