<?php

namespace App\Services;

use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class InfrastructureService
{
    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        // Traemos el servicio junto con el proyecto al que pertenece
        return Service::with('project')->latest()->paginate($perPage);
    }

    public function createService(array $data): Service
    {
        // REGLA DE NEGOCIO: Si no envían fecha de vencimiento, le sumamos 1 año exacto al día de hoy.
        if (empty($data['expiration_date'])) {
            $data['expiration_date'] = Carbon::now()->addYear()->toDateString();
        }

        // Si no mandan estado, por defecto es 'active'
        if (empty($data['status'])) {
            $data['status'] = \App\Enums\ServiceStatusEnum::ACTIVE->value;
        }

        return Service::create($data);
    }

    public function updateService(Service $service, array $data): Service
    {
        $service->update($data);
        return $service;
    }

    public function deleteService(Service $service): void
    {
        $service->delete();
    }

    /**
     * Obtiene los servicios que vencen en los próximos X días.
     */
    public function getExpiringServices(int $daysWarning = 7)
    {
        $targetDate = \Carbon\Carbon::now()->addDays($daysWarning)->toDateString();

        // Reutilizamos el Eager Loading para traer los datos del cliente y proyecto
        return Service::with(['project.client'])
            ->where('status', \App\Enums\ServiceStatusEnum::ACTIVE->value)
            ->whereDate('expiration_date', '<=', $targetDate)
            ->get();
    }
}