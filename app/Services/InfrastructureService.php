<?php

namespace App\Services;

use App\Enums\ServiceStatusEnum;
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
            $data['status'] = ServiceStatusEnum::ACTIVE->value;
        }

        // Valor por defecto para el ciclo de facturación
        if (empty($data['billing_cycle'])) {
            $data['billing_cycle'] = 'monthly';
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
     * Marca como vencidos los servicios que llevan demasiado tiempo sin renovarse.
     * Al salir de 'active' dejan de entrar al escaneo de recordatorios.
     *
     * @return int Cuántos servicios se dieron de baja.
     */
    public function expireOverdueServices(int $graceDays = 30): int
    {
        $cutoff = Carbon::now()->subDays($graceDays)->toDateString();

        return Service::where('status', ServiceStatusEnum::ACTIVE->value)
            ->whereDate('expiration_date', '<', $cutoff)
            ->update(['status' => ServiceStatusEnum::EXPIRED->value]);
    }

    /**
     * Extiende la vigencia de un servicio un ciclo de facturación completo.
     * Se usa cuando se confirma el pago de una renovación.
     */
    public function renewService(Service $service): Service
    {
        // Si aún no vence, encadenamos desde su fecha actual para no regalar días.
        // Si ya venció, arrancamos el nuevo ciclo desde hoy.
        $base = $service->expiration_date->isFuture()
            ? $service->expiration_date->copy()
            : Carbon::now();

        $newExpiration = match ($service->billing_cycle) {
            'monthly' => $base->addMonth(),
            'quarterly' => $base->addMonths(3),
            'annually' => $base->addYear(),
            'biennially' => $base->addYears(2),
            default => null, // 'one-time' no se renueva
        };

        if ($newExpiration) {
            $service->update([
                'expiration_date' => $newExpiration->toDateString(),
                'status' => ServiceStatusEnum::ACTIVE->value,
            ]);
        }

        return $service;
    }

    /**
     * Obtiene los servicios que vencen en los próximos X días.
     */
    public function getExpiringServices(int $daysWarning = 7)
    {
        $targetDate = Carbon::now()->addDays($daysWarning)->toDateString();

        // Reutilizamos el Eager Loading para traer los datos del cliente y proyecto
        return Service::with(['project.client'])
            ->where('status', ServiceStatusEnum::ACTIVE->value)
            ->whereDate('expiration_date', '<=', $targetDate)
            ->get();
    }
}
