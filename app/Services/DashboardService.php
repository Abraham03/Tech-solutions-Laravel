<?php

namespace App\Services;

use App\Enums\PaymentStatusEnum;
use App\Enums\ProjectStatusEnum;
use App\Enums\ServiceStatusEnum;
use App\Models\Client;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getAdminSummary(): array
    {
        return [
            'metrics' => [
                // Métricas Base
                'totalClients' => $this->getTotalClientsCount(),
                'activeProjects' => $this->getActiveProjectsCount(),
                'pendingInvoices' => $this->getPendingInvoicesCount(),

                // Métricas Financieras
                'mrr' => $this->calculateRealMRR(),
                'monthlyProfit' => $this->calculateMonthlyProfit(),
                'totalReceivable' => $this->calculateTotalReceivable(),

                // NUEVO: Proyección anual basada en MRR actual
                'annualProjection' => $this->calculateRealMRR() * 12,

                // NUEVO: Total histórico cobrado (todos los pagos completados)
                'totalCollected' => $this->calculateTotalCollected(),

                // NUEVO: Conteo de servicios por vencer en próximos 30 días
                'servicesExpiringSoon' => $this->getExpiringServices(30, countOnly: true),
            ],

            // Data Arrays para el Frontend
            'recentProjects' => $this->getRecentProjects(),
            'expiringServices' => $this->getExpiringServices(30),
            'revenueChart' => $this->getRevenueHistory(),

            // NUEVO: Historial de ingresos agrupado por año
            'revenueByYear' => $this->getRevenueByYear(),

            // NUEVO: Ingresos del mes actual
            'revenueThisMonth' => $this->getRevenueByPeriod('month'),

            // NUEVO: Ingresos del año actual
            'revenueThisYear' => $this->getRevenueByPeriod('year'),

            // NUEVO: Notificaciones recientes enviadas
            'recentNotifications' => $this->getRecentNotifications(),

            // NUEVO: Resumen de notificaciones por canal
            'notificationsSummary' => $this->getNotificationsSummary(),

            // NUEVO: Margen por servicio activo
            'serviceMargins' => $this->getServiceMargins(),

            // NUEVO: LTV (valor de vida) por cliente
            'clientLTV' => $this->getClientLTV(),
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
                'monthly' => $service->price_mxn,
                'quarterly' => $service->price_mxn / 3,
                'annually' => $service->price_mxn / 12,
                'biennially' => $service->price_mxn / 24,
                default => 0,
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
                'monthly' => $margin,
                'quarterly' => $margin / 3,
                'annually' => $margin / 12,
                'biennially' => $margin / 24,
                default => 0,
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

        return round((float) $projects->sum(fn ($p) => $p->balance), 2);
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
            'year' => $query->whereYear('paid_at', now()->year),
            default => $query,
        };

        return [
            'total' => round((float) $query->sum('amount'), 2),
            'count' => $query->count(),
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
                DB::raw($this->sqlYear('paid_at').' as year')
            )
            ->groupBy('year')
            ->orderBy('year', 'desc')
            ->get()
            ->map(fn ($r) => [
                'year' => $r->year,
                'total' => (float) $r->total,
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
                DB::raw($this->sqlYearMonth('paid_at').' as month')
            )
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->take(12)
            ->get()
            ->map(fn ($r) => [
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

        return $query->get()->map($this->mapExpiringService())->toArray();
    }

    /**
     * NUEVO: Margen de ganancia por cada servicio activo.
     */
    private function getServiceMargins(): array
    {
        return $this->serviceMarginsQuery()->get()->map($this->mapServiceMargin())->toArray();
    }

    // ==========================================
    // PROYECTOS
    // ==========================================

    private function getRecentProjects(): array
    {
        return $this->recentProjectsQuery()->take(5)->get()->map($this->mapProject())->toArray();
    }

    // ==========================================
    // NOTIFICACIONES
    // ==========================================

    /**
     * NUEVO: Últimas 20 notificaciones enviadas con datos del cliente y servicio.
     */
    private function getRecentNotifications(): array
    {
        return $this->recentNotificationsQuery()->take(20)->get()->map($this->mapNotification())->toArray();
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
            'email' => (int) ($counts['email_invoice'] ?? 0),
            'push' => (int) ($counts['push_alert'] ?? 0),
            'total' => array_sum($counts),
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
        return $this->clientLtvQuery()->get()->map($this->mapClientLtv())->toArray();
    }

    // ==========================================
    // LISTADOS PAGINADOS DE LAS PESTANAS
    //
    // El resumen (getAdminSummary) sigue devolviendo recortes cortos para el
    // primer pintado. Estos metodos sirven a cada pestana su propia pagina, para
    // que el dashboard deje de cargarlo todo de golpe cuando las listas crezcan.
    //
    // Cada lista comparte consulta y mapeo con su version del resumen, asi no
    // hay dos formas distintas de la misma fila segun por donde se pida.
    // ==========================================

    public function paginatedRecentProjects(int $perPage = 15): LengthAwarePaginator
    {
        return $this->recentProjectsQuery()->paginate($perPage)->withQueryString()->through($this->mapProject());
    }

    public function paginatedExpiringServices(int $perPage = 15, int $days = 30): LengthAwarePaginator
    {
        return $this->expiringServicesQuery($days)->paginate($perPage)->withQueryString()->through($this->mapExpiringService());
    }

    public function paginatedServiceMargins(int $perPage = 15): LengthAwarePaginator
    {
        return $this->serviceMarginsQuery()->paginate($perPage)->withQueryString()->through($this->mapServiceMargin());
    }

    public function paginatedNotifications(int $perPage = 15, ?string $type = null): LengthAwarePaginator
    {
        return $this->recentNotificationsQuery()
            // El filtro por canal se resuelve en la base y no en el navegador:
            // filtrar en el cliente solo miraria la pagina cargada.
            ->when($type, fn ($q) => $q->where('type', $type))
            ->paginate($perPage)
            ->withQueryString()
            ->through($this->mapNotification());
    }

    public function paginatedClientLtv(int $perPage = 15): LengthAwarePaginator
    {
        return $this->clientLtvQuery()->paginate($perPage)->withQueryString()->through($this->mapClientLtv());
    }

    // ---------------------------------------------------------------- consultas

    private function recentProjectsQuery()
    {
        return Project::with('client')->orderBy('created_at', 'desc');
    }

    private function expiringServicesQuery(int $days = 30)
    {
        return Service::with('project.client')
            ->where('status', ServiceStatusEnum::ACTIVE)
            ->whereNotNull('expiration_date')
            ->whereBetween('expiration_date', [now()->startOfDay(), now()->addDays($days)->endOfDay()])
            ->orderBy('expiration_date', 'asc');
    }

    private function serviceMarginsQuery()
    {
        return Service::with('project.client')->where('status', ServiceStatusEnum::ACTIVE);
    }

    private function recentNotificationsQuery()
    {
        return NotificationLog::with(['client', 'service'])->orderBy('sent_at', 'desc');
    }

    private function clientLtvQuery()
    {
        return Client::withSum(['payments' => function ($q) {
            $q->where('status', PaymentStatusEnum::COMPLETED);
        }], 'amount')->orderByDesc('payments_sum_amount');
    }

    // ---------------------------------------------------------------- mapeos

    private function mapProject(): callable
    {
        return fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'type' => $p->type->value ?? $p->type,
            'status' => $p->status->value ?? $p->status,
            'amount' => (float) $p->total_price,
            'balance' => (float) $p->balance,
            'paid_pct' => $p->total_price > 0
                ? round(($p->total_price - $p->balance) / $p->total_price * 100, 1)
                : 100,
        ];
    }

    private function mapExpiringService(): callable
    {
        return function ($service) {
            $daysLeft = (int) now()->startOfDay()->diffInDays($service->expiration_date, false);

            return [
                'id' => $service->id,
                'name' => $service->name,
                'type' => $service->type,
                'provider' => $service->provider,
                'billing_cycle' => $service->billing_cycle,
                'client_name' => $service->project->client->name ?? 'Sin Cliente',
                'client_phone' => $service->project->client->phone_number ?? null,
                'expiration_date' => $service->expiration_date->format('Y-m-d'),
                'days_left' => $daysLeft,
                'urgency' => match (true) {
                    $daysLeft <= 0 => 'expired',
                    $daysLeft <= 7 => 'critical',
                    $daysLeft <= 15 => 'warning',
                    default => 'ok',
                },
                'price_mxn' => (float) $service->price_mxn,
                'cost_mxn' => (float) $service->cost_mxn,
                'profit_margin' => round((float) ($service->price_mxn - $service->cost_mxn), 2),
            ];
        };
    }

    private function mapServiceMargin(): callable
    {
        return function ($service) {
            $margin = $service->price_mxn - $service->cost_mxn;
            $divisor = match ($service->billing_cycle) {
                'monthly' => 1,
                'quarterly' => 3,
                'annually' => 12,
                'biennially' => 24,
                default => 1,
            };

            return [
                'id' => $service->id,
                'name' => $service->name,
                'client_name' => $service->project->client->name ?? 'Sin cliente',
                'billing_cycle' => $service->billing_cycle,
                'price_mxn' => (float) $service->price_mxn,
                'cost_mxn' => (float) $service->cost_mxn,
                'margin_total' => round((float) $margin, 2),
                'mrr' => round((float) $service->price_mxn / $divisor, 2),
                'margin_monthly' => round((float) $margin / $divisor, 2),
            ];
        };
    }

    private function mapNotification(): callable
    {
        return fn ($n) => [
            'id' => $n->id,
            'type' => $n->type,
            'client_name' => $n->client->name ?? 'Sin cliente',
            'service_name' => $n->service->name ?? null,
            'message_body' => $n->message_body,
            'sent_at' => Carbon::parse($n->sent_at)->format('Y-m-d H:i'),
            'sent_ago' => Carbon::parse($n->sent_at)->diffForHumans(),
        ];
    }

    private function mapClientLtv(): callable
    {
        return fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'contact_name' => $c->contact_name,
            'ltv' => round((float) ($c->payments_sum_amount ?? 0), 2),
        ];
    }

    // ---------------------------------------------------------------- fechas SQL

    /**
     * YEAR() y DATE_FORMAT() son de MySQL. Estaban escritas a pelo, asi que
     * cualquier entorno con otro motor -las pruebas usan SQLite- reventaba con
     * "no such function". Estas dos devuelven la expresion del motor en uso.
     */
    private function sqlYear(string $columna): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%Y', {$columna}) AS INTEGER)"
            : "YEAR({$columna})";
    }

    private function sqlYearMonth(string $columna): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$columna})"
            : "DATE_FORMAT({$columna}, '%Y-%m')";
    }
}
