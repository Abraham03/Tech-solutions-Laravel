<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Project;
use App\Models\Service;
use App\Models\Payment;
use App\Enums\ProjectStatusEnum;
use App\Enums\ServiceStatusEnum;
use App\Enums\PaymentStatusEnum;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getAdminSummary(): array
    {
        return [
            'metrics' => [
                // Métricas Base
                'activeClients' => $this->getActiveClientsCount(),
                'activeProjects' => $this->getActiveProjectsCount(),
                'pendingInvoices' => $this->getPendingInvoicesCount(),
                
                // Nuevas Métricas Financieras (Cruce Inteligente)
                'mrr' => $this->calculateRealMRR(),
                'monthlyProfit' => $this->calculateMonthlyProfit(),
                'totalReceivable' => $this->calculateTotalReceivable(),
            ],
            // Data Arrays para el Frontend
            'recentProjects' => $this->getRecentProjects(),
            'expiringServices' => $this->getExpiringServices(),
            'revenueChart' => $this->getRevenueHistory(),
        ];
    }

    // ==========================================
    // MÉTRICAS BASE ORIGINALES
    // ==========================================

    private function getActiveClientsCount(): int
    {
        return Client::count(); 
    }

    private function getActiveProjectsCount(): int
    {
        return Project::where('status', ProjectStatusEnum::DEVELOPMENT)->count();
    }

    private function getPendingInvoicesCount(): int
    {
        return Payment::where('status', PaymentStatusEnum::PENDING)->count();
    }

    // ==========================================
    // CÁLCULOS FINANCIEROS Y MÁRGENES
    // ==========================================

    /**
     * MRR Real: Suma los precios dividiéndolos según su ciclo de facturación.
     */
    private function calculateRealMRR(): float
    {
        $services = Service::where('status', ServiceStatusEnum::ACTIVE)->get();
        $mrr = 0;

        foreach ($services as $service) {
            $mrr += match ($service->billing_cycle) {
                'monthly'    => $service->price_mxn,
                'quarterly'  => $service->price_mxn / 3,  // Póliza cada 3 meses
                'annually'   => $service->price_mxn / 12,
                'biennially' => $service->price_mxn / 24,
                default      => 0, // Pagos únicos no suman al MRR
            };
        }

        return (float) $mrr;
    }

    /**
     * Ganancia Mensual: Margen (Precio - Costo) llevado a formato mensual.
     */
    private function calculateMonthlyProfit(): float
    {
        $services = Service::where('status', ServiceStatusEnum::ACTIVE)->get();
        $profit = 0;

        foreach ($services as $service) {
            $profit += match ($service->billing_cycle) {
                'monthly'    => $service->margin,
                'quarterly'  => $service->margin / 3,  // Ganancia de la póliza dividida en 3 meses
                'annually'   => $service->margin / 12,
                'biennially' => $service->margin / 24,
                default      => 0,
            };
        }

        return (float) $profit;
    }

    /**
     * Cruce de Proyectos vs Pagos: Dinero pendiente por cobrar.
     */
    private function calculateTotalReceivable(): float
    {
        // Eager Loading: Trae proyectos incompletos y SOLO sus pagos exitosos para no ahogar la DB
        $projects = Project::with(['payments' => function ($query) {
            $query->where('status', PaymentStatusEnum::COMPLETED);
        }])->where('status', '!=', ProjectStatusEnum::COMPLETED)->get();

        // Usamos el Accessor $project->balance que definimos en el Modelo
        return (float) $projects->sum(fn($project) => $project->balance);
    }

    // ==========================================
    // LISTAS Y DATOS ESTRUCTURADOS
    // ==========================================

    private function getRecentProjects(): array
    {
        return Project::orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'type' => $project->type->value ?? $project->type,
                    'status' => $project->status->value ?? $project->status,
                    'amount' => (float) $project->total_price,
                    // Agregamos el balance para que el Front pueda pintar "Resta $X"
                    'balance' => (float) $project->balance, 
                ];
            })->toArray();
    }

    /**
     * Alertas de Vencimiento: Servicios en los próximos 30 días.
     */
    private function getExpiringServices(): array
    {
        // 1. Cambiamos 'client' por 'project.client'
        return Service::with('project.client')
            ->where('status', ServiceStatusEnum::ACTIVE)
            ->whereNotNull('expiration_date')
            ->whereBetween('expiration_date', [now(), now()->addDays(30)])
            ->orderBy('expiration_date', 'asc')
            ->get()
            ->map(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    // 2. Navegamos a través del proyecto para llegar al nombre del cliente
                    'client_name' => $service->project->client->name ?? 'Sin Cliente',
                    'expiration_date' => $service->expiration_date->format('Y-m-d'),
                    'profit_margin' => (float) $service->margin,
                ];
            })->toArray();
    }

    /**
     * Historial para Gráficas: Agrupación SQL de pagos por mes.
     */
    private function getRevenueHistory(): array
    {
        return Payment::where('status', PaymentStatusEnum::COMPLETED)
            ->select(
                DB::raw('SUM(amount) as total'),
                DB::raw("DATE_FORMAT(payment_date, '%Y-%m') as month")
            )
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->take(6)
            ->get()
            ->toArray();
    }
}