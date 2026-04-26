<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Project;
use App\Models\Service;
use App\Models\Payment;
use App\Models\NotificationLog;
use App\Enums\ProjectStatusEnum;
use App\Enums\ServiceStatusEnum;
use App\Enums\PaymentStatusEnum;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardService
{
    public function getAdminSummary(): array
    {
        return [
            'metrics' => [
                // Métricas Base
                'totalClients'       => $this->getTotalClientsCount(),
                'activeProjects'     => $this->getActiveProjectsCount(),
                'pendingInvoices'    => $this->getPendingInvoicesCount(),

                // Métricas Financieras
                'mrr'                => $this->calculateRealMRR(),
                'monthlyProfit'      => $this->calculateMonthlyProfit(),
                'totalReceivable'    => $this->calculateTotalReceivable(),

                // NUEVO: Proyección anual basada en MRR actual
                'annualProjection'   => $this->calculateRealMRR() * 12,

                // NUEVO: Total histórico cobrado (todos los pagos completados)
                'totalCollected'     => $this->calculateTotalCollected(),

                // NUEVO: Conteo de servicios por vencer en próximos 30 días
                'servicesExpiringSoon' => $this->getExpiringServices(30, countOnly: true),
            ],

            // Data Arrays para el Frontend
            'recentProjects'     => $this->getRecentProjects(),
            'expiringServices'   => $this->getExpiringServices(30),
            'revenueChart'       => $this->getRevenueHistory(),

            // NUEVO: Historial de ingresos agrupado por año
            'revenueByYear'      => $this->getRevenueByYear(),

            // NUEVO: Ingresos del mes actual
            'revenueThisMonth'   => $this->getRevenueByPeriod('month'),

            // NUEVO: Ingresos del año actual
            'revenueThisYear'    => $this->getRevenueByPeriod('year'),

            // NUEVO: Notificaciones recientes enviadas
            'recentNotifications' => $this->getRecentNotifications(),

            // NUEVO: Resumen de notificaciones por canal
            'notificationsSummary' => $this->getNotificationsSummary(),

            // NUEVO: Margen por servicio activo
            'serviceMargins'     => $this->getServiceMargins(),

            // NUEVO: LTV (valor de vida) por cliente
            'clientLTV'          => $this->getClientLTV(),
        ];
    }

    // ==========================================
    // MÉTRICAS BASE
    // ==========================================

    private function getTotalClientsCount(): int
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
    // CÁLCULOS FINANCIEROS
    // ==========================================

    /**
     * MRR Real: precio dividido según ciclo de facturación.
     */
    private function calculateRealMRR(): float
    {
        $services = Service::where('status', ServiceStatusEnum::ACTIVE)->get();
        $mrr = 0;

        foreach ($services as $service) {
            $mrr += match ($service->billing_cycle) {
                'monthly'    => $service->price_mxn,
                'quarterly'  => $service->price_mxn / 3,
                'annually'   => $service->price_mxn / 12,
                'biennially' => $service->price_mxn / 24,
                default      => 0,
            };
        }

        return round((float) $mrr, 2);
    }

    /**
     * Ganancia mensual: margen (precio - costo) llevado a mensual.
     */
    private function calculateMonthlyProfit(): float
    {
        $services = Service::where('status', ServiceStatusEnum::ACTIVE)->get();
        $profit = 0;

        foreach ($services as $service) {
            $margin = $service->price_mxn - $service->cost_mxn;
            $profit += match ($service->billing_cycle) {
                'monthly'    => $margin,
                'quarterly'  => $margin / 3,
                'annually'   => $margin / 12,
                'biennially' => $margin / 24,
                default      => 0,
            };
        }

        return round((float) $profit, 2);
    }

    /**
     * Saldo pendiente: proyectos no completados vs pagos recibidos.
     */
    private function calculateTotalReceivable(): float
    {
        $projects = Project::with(['payments' => function ($q) {
            $q->where('status', PaymentStatusEnum::COMPLETED);
        }])->where('status', '!=', ProjectStatusEnum::COMPLETED)->get();

        return round((float) $projects->sum(fn($p) => $p->balance), 2);
    }

    /**
     * NUEVO: Total histórico de pagos completados (todos los tiempos).
     */
    private function calculateTotalCollected(): float
    {
        return round((float) Payment::where('status', PaymentStatusEnum::COMPLETED)->sum('amount'), 2);
    }

    // ==========================================
    // HISTORIAL DE INGRESOS — FILTROS TEMPORALES
    // ==========================================

    /**
     * NUEVO: Ingresos filtrados por período ('month' | 'year' | 'all').
     */
    private function getRevenueByPeriod(string $period): array
    {
        $query = Payment::where('status', PaymentStatusEnum::COMPLETED);

        $query = match ($period) {
            'month' => $query->whereYear('paid_at', now()->year)
                             ->whereMonth('paid_at', now()->month),
            'year'  => $query->whereYear('paid_at', now()->year),
            default => $query,
        };

        return [
            'total'  => round((float) $query->sum('amount'), 2),
            'count'  => $query->count(),
            'period' => $period,
        ];
    }

    /**
     * NUEVO: Ingresos totales agrupados por año.
     */
    private function getRevenueByYear(): array
    {
        return Payment::where('status', PaymentStatusEnum::COMPLETED)
            ->select(
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as payments_count'),
                DB::raw("YEAR(paid_at) as year")
            )
            ->groupBy('year')
            ->orderBy('year', 'desc')
            ->get()
            ->map(fn($r) => [
                'year'           => $r->year,
                'total'          => (float) $r->total,
                'payments_count' => (int) $r->payments_count,
            ])
            ->toArray();
    }

    /**
     * Historial mensual para gráfica (últimos 12 meses).
     */
    private function getRevenueHistory(): array
    {
        return Payment::where('status', PaymentStatusEnum::COMPLETED)
            ->select(
                DB::raw('SUM(amount) as total'),
                DB::raw("DATE_FORMAT(paid_at, '%Y-%m') as month")
            )
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->take(12)
            ->get()
            ->map(fn($r) => [
                'month' => $r->month,
                'total' => (float) $r->total,
            ])
            ->toArray();
    }

    // ==========================================
    // SERVICIOS
    // ==========================================

    /**
     * Servicios próximos a vencer. Soporta modo count-only para métricas.
     */
    private function getExpiringServices(int $days = 30, bool $countOnly = false): array|int
    {
        $query = Service::with('project.client')
            ->where('status', ServiceStatusEnum::ACTIVE)
            ->whereNotNull('expiration_date')
            ->whereBetween('expiration_date', [now()->startOfDay(), now()->addDays($days)->endOfDay()])
            ->orderBy('expiration_date', 'asc');

        if ($countOnly) {
            return $query->count();
        }

        return $query->get()->map(function ($service) {
            $daysLeft = (int) now()->startOfDay()->diffInDays($service->expiration_date, false);
            return [
                'id'             => $service->id,
                'name'           => $service->name,
                'type'           => $service->type,
                'provider'       => $service->provider,
                'billing_cycle'  => $service->billing_cycle,
                'client_name'    => $service->project->client->name ?? 'Sin Cliente',
                'client_phone'   => $service->project->client->phone_number ?? null,
                'expiration_date'=> $service->expiration_date->format('Y-m-d'),
                'days_left'      => $daysLeft,
                // NUEVO: semáforo de urgencia
                'urgency'        => match(true) {
                    $daysLeft <= 0  => 'expired',
                    $daysLeft <= 7  => 'critical',
                    $daysLeft <= 15 => 'warning',
                    default         => 'ok',
                },
                'price_mxn'      => (float) $service->price_mxn,
                'cost_mxn'       => (float) $service->cost_mxn,
                'profit_margin'  => round((float) ($service->price_mxn - $service->cost_mxn), 2),
            ];
        })->toArray();
    }

    /**
     * NUEVO: Margen de ganancia por cada servicio activo.
     */
    private function getServiceMargins(): array
    {
        return Service::with('project.client')
            ->where('status', ServiceStatusEnum::ACTIVE)
            ->get()
            ->map(function ($service) {
                $margin     = $service->price_mxn - $service->cost_mxn;
                $divisor    = match ($service->billing_cycle) {
                    'monthly'    => 1,
                    'quarterly'  => 3,
                    'annually'   => 12,
                    'biennially' => 24,
                    default      => 1,
                };
                return [
                    'id'           => $service->id,
                    'name'         => $service->name,
                    'client_name'  => $service->project->client->name ?? '—',
                    'billing_cycle'=> $service->billing_cycle,
                    'price_mxn'    => (float) $service->price_mxn,
                    'cost_mxn'     => (float) $service->cost_mxn,
                    'margin_total' => round((float) $margin, 2),
                    // MRR y margen mensualizado
                    'mrr'          => round((float) $service->price_mxn / $divisor, 2),
                    'margin_monthly'=> round((float) $margin / $divisor, 2),
                ];
            })->toArray();
    }

    // ==========================================
    // PROYECTOS
    // ==========================================

    private function getRecentProjects(): array
    {
        return Project::with('client')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(fn($p) => [
                'id'      => $p->id,
                'name'    => $p->name,
                'type'    => $p->type->value ?? $p->type,
                'status'  => $p->status->value ?? $p->status,
                'amount'  => (float) $p->total_price,
                'balance' => (float) $p->balance,
                // NUEVO: porcentaje cobrado
                'paid_pct'=> $p->total_price > 0
                    ? round(($p->total_price - $p->balance) / $p->total_price * 100, 1)
                    : 100,
            ])->toArray();
    }

    // ==========================================
    // NOTIFICACIONES
    // ==========================================

    /**
     * NUEVO: Últimas 20 notificaciones enviadas con datos del cliente y servicio.
     */
    private function getRecentNotifications(): array
    {
        return NotificationLog::with(['client', 'service'])
            ->orderBy('sent_at', 'desc')
            ->take(20)
            ->get()
            ->map(fn($n) => [
                'id'           => $n->id,
                'type'         => $n->type,
                'client_name'  => $n->client->name ?? '—',
                'service_name' => $n->service->name ?? null,
                'message_body' => $n->message_body,
                'sent_at'      => Carbon::parse($n->sent_at)->format('Y-m-d H:i'),
                // NUEVO: tiempo relativo legible
                'sent_ago'     => Carbon::parse($n->sent_at)->diffForHumans(),
            ])->toArray();
    }

    /**
     * NUEVO: Conteo de notificaciones por canal (WhatsApp / Email / Push).
     */
    private function getNotificationsSummary(): array
    {
        $counts = NotificationLog::select('type', DB::raw('COUNT(*) as total'))
            ->groupBy('type')
            ->pluck('total', 'type')
            ->toArray();

        return [
            'whatsapp' => (int) ($counts['whatsapp_reminder'] ?? 0),
            'email'    => (int) ($counts['email_invoice']     ?? 0),
            'push'     => (int) ($counts['push_alert']        ?? 0),
            'total'    => array_sum($counts),
        ];
    }

    // ==========================================
    // CRUCE DE DATOS — LTV POR CLIENTE
    // ==========================================

    /**
     * NUEVO: Valor de vida del cliente (suma de todos sus pagos completados).
     */
    private function getClientLTV(): array
    {
        return Client::withSum(['payments' => function ($q) {
            $q->where('status', PaymentStatusEnum::COMPLETED);
        }], 'amount')
            ->orderByDesc('payments_sum_amount')
            ->get()
            ->map(fn($c) => [
                'id'           => $c->id,
                'name'         => $c->name,
                'contact_name' => $c->contact_name,
                'ltv'          => round((float) ($c->payments_sum_amount ?? 0), 2),
            ])->toArray();
    }
}