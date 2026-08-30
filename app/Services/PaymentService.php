<?php

namespace App\Services;

use App\Enums\PaymentStatusEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\ProjectStatusEnum;
use App\Models\Payment;
use App\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function getAllPaginated(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return Payment::with(['client', 'project', 'service'])
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('payment_method', 'like', "%{$search}%")
                    ->orWhere('payment_type', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    // Util para rastrear un cobro concreto desde el panel de Stripe.
                    ->orWhere('stripe_payment_intent_id', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createPayment(array $data): Payment
    {
        // Si no mandan fecha de pago pero el estatus es completado, le ponemos la fecha actual
        if (empty($data['paid_at']) && ($data['status'] ?? '') === PaymentStatusEnum::COMPLETED->value) {
            $data['paid_at'] = now();
        }

        // REGLA DE NEGOCIO: Validar saldos si el pago abona al proyecto y está completado.
        // Las renovaciones (dominio, hosting) son cobros recurrentes independientes del
        // precio del proyecto, así que no se validan ni suman contra su total.
        $esAbonoAProyecto = ! empty($data['project_id'])
            && ($data['payment_type'] ?? '') !== PaymentTypeEnum::RENEWAL->value
            && ($data['status'] ?? '') === PaymentStatusEnum::COMPLETED->value;

        if ($esAbonoAProyecto) {
            $project = Project::findOrFail($data['project_id']);

            // Calculamos cuánto se ha pagado hasta ahora (sin contar renovaciones)
            $currentPaid = $project->payments()
                ->where('status', PaymentStatusEnum::COMPLETED->value)
                ->where('payment_type', '!=', PaymentTypeEnum::RENEWAL->value)
                ->sum('amount');

            $newTotal = $currentPaid + $data['amount'];
            $saldoPendiente = $project->total_price - $currentPaid;

            // Bloqueamos si el abono supera lo que debe
            if ($newTotal > $project->total_price) {
                throw ValidationException::withMessages([
                    'amount' => 'El abono excede el costo del proyecto. Saldo pendiente: $'.number_format($saldoPendiente, 2),
                ]);
            }

            // Transacción: Guardamos el pago y, si liquida la deuda, cerramos el proyecto.
            return DB::transaction(function () use ($data, $project, $newTotal) {
                $payment = Payment::create($data);

                if ($newTotal == $project->total_price) {
                    $project->update(['status' => ProjectStatusEnum::COMPLETED->value]);
                }

                return $payment;
            });
        }

        // Si es un pago de servicios, o si está pendiente, lo creamos normalmente
        return Payment::create($data);
    }

    public function updatePayment(Payment $payment, array $data): Payment
    {
        // En un sistema estricto, no deberíamos dejar editar pagos completados.
        // Pero para flexibilidad, lo actualizamos.
        $payment->update($data);

        return $payment;
    }

    public function deletePayment(Payment $payment): void
    {
        $payment->delete();
    }
}
