<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Project;
use App\Models\Service;
use App\Models\Payment;
use App\Enums\ProjectStatusEnum;
use App\Enums\ServiceStatusEnum;
use App\Enums\PaymentStatusEnum;

class DashboardService
{
    public function getAdminSummary(): array
    {
        return [
            'metrics' => [
                'mrr' => $this->calculateMRR(),
                'activeClients' => $this->getActiveClientsCount(),
                'activeProjects' => $this->getActiveProjectsCount(),
                'pendingInvoices' => $this->getPendingInvoicesCount(),
            ],
            'recentProjects' => $this->getRecentProjects(),
        ];
    }

    private function calculateMRR(): float
    {
        // Suma el precio (price_mxn) solo de los servicios con estado ACTIVE
        return (float) Service::where('status', ServiceStatusEnum::ACTIVE)->sum('price_mxn');
    }

    private function getActiveClientsCount(): int
    {
        return Client::count(); 
    }

    private function getActiveProjectsCount(): int
    {
        // Los proyectos "Activos" son los que están en DEVELOPMENT
        return Project::where('status', ProjectStatusEnum::DEVELOPMENT)->count();
    }

    private function getPendingInvoicesCount(): int
    {
        return Payment::where('status', PaymentStatusEnum::PENDING)->count();
    }

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
                ];
            })->toArray();
    }
}