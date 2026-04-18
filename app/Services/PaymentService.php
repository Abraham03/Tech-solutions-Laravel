<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Project;
use App\Enums\PaymentStatusEnum;
use App\Enums\ProjectStatusEnum;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentService
{
    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return Payment::with(['client', 'project', 'service'])->latest()->paginate($perPage);
    }

    public function createPayment(array $data): Payment
    {
        // Si no mandan fecha de pago pero el estatus es completado, le ponemos la fecha actual
        if (empty($data['paid_at']) && ($data['status'] ?? '') === PaymentStatusEnum::COMPLETED->value) {
            $data['paid_at'] = now();
        }

        // REGLA DE NEGOCIO: Validar saldos si el pago es para un proyecto y está completado
        if (!empty($data['project_id']) && ($data['status'] ?? '') === PaymentStatusEnum::COMPLETED->value) {
            $project = Project::findOrFail($data['project_id']);
            
            // Calculamos cuánto se ha pagado hasta ahora
            $currentPaid = $project->payments()
                ->where('status', PaymentStatusEnum::COMPLETED->value)
                ->sum('amount');
                
            $newTotal = $currentPaid + $data['amount'];
            $saldoPendiente = $project->total_price - $currentPaid;

            // Bloqueamos si el abono supera lo que debe
            if ($newTotal > $project->total_price) {
                throw ValidationException::withMessages([
                    'amount' => "El abono excede el costo del proyecto. Saldo pendiente: $" . number_format($saldoPendiente, 2)
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