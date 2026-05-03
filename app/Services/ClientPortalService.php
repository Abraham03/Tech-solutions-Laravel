<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use App\Models\Project;
use App\Models\Service;
use App\Models\Payment;
use App\Enums\ProjectStatusEnum;
use App\Enums\ServiceStatusEnum;

class ClientPortalService
{
    /**
     * Obtiene el perfil de cliente asociado al usuario autenticado.
     * Si no existe, lanza un error 403.
     */
    private function getAuthenticatedClient()
    {
        $user = Auth::user();

        // Utilizamos la relación 'clientProfile' que definiste en el modelo User
        if (!$user->clientProfile) {
            abort(403, 'Tu cuenta no tiene un perfil de cliente asignado.');
        }

        return $user->clientProfile;
    }

    /**
     * Genera el resumen de datos para el Dashboard del Cliente.
     */
    public function getDashboardSummary(): array
    {
        $client = $this->getAuthenticatedClient();

        // 1. Calculamos el total de la deuda (Suma de todos los proyectos)
        $totalProjectsPrice = Project::where('client_id', $client->id)->sum('total_price');

        // 2. Calculamos el total pagado (Suma de todos los pagos con estado 'completed')
        $totalPaid = Payment::where('client_id', $client->id)
                            ->where('status', 'completed') // ¡Importante! Solo restamos lo que ya se pagó
                            ->sum('amount');

        // 3. El saldo pendiente real es la resta (Deuda - Pagado)
        $pendingBalance = $totalProjectsPrice - $totalPaid;

        return [
            'profile' => [
                'name' => $client->name,
                'contact_name' => $client->contact_name,
                'email' => $client->email,
            ],
            'metrics' => [
                'active_projects' => Project::where('client_id', $client->id)
                                        ->where('status', '!=', ProjectStatusEnum::COMPLETED)
                                        ->count(),
                'active_services' => Service::whereHas('project', function($q) use ($client) {
                                            $q->where('client_id', $client->id);
                                        })->where('status', ServiceStatusEnum::ACTIVE)->count(),
                // Asignamos la variable calculada aquí
                'pending_balance' => $pendingBalance,
            ],
            // Últimos 5 proyectos
            'recent_projects' => Project::where('client_id', $client->id)
                                    ->orderBy('created_at', 'desc')
                                    ->take(5)
                                    ->get(['id', 'name', 'type', 'status', 'total_price']),
            // Servicios activos
            'active_services' => Service::with('project:id,name')
                                    ->whereHas('project', function($q) use ($client) {
                                        $q->where('client_id', $client->id);
                                    })
                                    ->where('status', ServiceStatusEnum::ACTIVE)
                                    ->get(['id', 'project_id', 'name', 'type', 'billing_cycle', 'price_mxn', 'expiration_date']),
            // Últimos 5 pagos realizados
            'recent_payments' => Payment::where('client_id', $client->id)
                                    ->with(['project:id,name', 'service:id,name'])
                                    ->orderBy('paid_at', 'desc')
                                    ->take(5)
                                    ->get(['id', 'project_id', 'service_id', 'amount', 'payment_method', 'status', 'paid_at']),
        ];
    }
}