<?php

namespace App\Services;

use App\Enums\ServiceStatusEnum;
use App\Enums\ServiceTypeEnum;
use App\Exceptions\BasketProException;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class InfrastructureService
{
    public function __construct(private readonly BasketProService $basketPro) {}

    public function getAllPaginated(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        // Traemos el servicio junto con el proyecto al que pertenece
        return Service::with('project')
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('provider', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('project', fn ($p) => $p->where('name', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
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

        // Los datos de la cuenta de BasketPro no son columnas del servicio y traen la
        // contraseña del admin: salen de $data antes de guardar nada.
        $basketpro = Arr::pull($data, 'basketpro');

        if ($data['type'] !== ServiceTypeEnum::BASKETPRO_SUBSCRIPTION->value) {
            return Service::create($data);
        }

        // Servicio y cuenta nacen juntos o no nace ninguno: si BasketPro rechaza el alta,
        // el rollback se lleva el servicio.
        return DB::transaction(function () use ($data, $basketpro): Service {
            $service = Service::create([
                ...$data,
                'basketpro_tenant_code' => $basketpro['account_code'],
                'basketpro_plan_code' => $basketpro['plan_code'],
            ]);

            $this->callBasketPro(fn () => $this->basketPro->createTenant([
                'name' => $service->project->client->name,
                'code' => $basketpro['account_code'],
                'database_name' => $basketpro['database_name'],
                'database_username' => $basketpro['database_username'],
                'database_password' => $basketpro['database_password'],
                'league_code' => $basketpro['league_code'],
                'league_name' => $basketpro['league_name'],
                'admin_name' => $basketpro['admin_name'],
                'admin_username' => $basketpro['admin_username'],
                'admin_password' => $basketpro['admin_password'],
                'plan_code' => $basketpro['plan_code'],
                'valid_until' => $service->expiration_date->toDateString(),
            ]));

            return $service;
        });
    }

    public function updateService(Service $service, array $data): Service
    {
        $planCode = Arr::pull($data, 'basketpro.plan_code');
        Arr::forget($data, 'basketpro');

        $isBasketPro = $service->type === ServiceTypeEnum::BASKETPRO_SUBSCRIPTION;

        // Cambiar el tipo dejaria una cuenta de BasketPro sin quien la cobre, o un servicio
        // de BasketPro sin cuenta.
        if (isset($data['type']) && ($data['type'] === ServiceTypeEnum::BASKETPRO_SUBSCRIPTION->value) !== $isBasketPro) {
            throw ValidationException::withMessages([
                'type' => 'Un servicio de BasketPro no cambia de tipo, ni otro servicio se vuelve de BasketPro: da de alta uno nuevo.',
            ]);
        }

        if (! $isBasketPro) {
            $service->update($data);

            return $service;
        }

        if ($planCode !== null) {
            $data['basketpro_plan_code'] = $planCode;
        }

        // Si BasketPro no acepta el cambio, aqui tampoco se guarda: los dos quedan como estaban.
        return DB::transaction(function () use ($service, $data): Service {
            $service->update($data);

            if ($service->wasChanged(['expiration_date', 'status', 'basketpro_plan_code'])) {
                $this->callBasketPro(fn () => $this->syncBasketProSubscription($service));
            }

            return $service;
        });
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

            if ($service->type === ServiceTypeEnum::BASKETPRO_SUBSCRIPTION) {
                // Aislado: el pago ya quedó guardado. Si BasketPro no se enteró, la
                // sincronización diaria (basketpro:sync-subscriptions) lo corrige.
                try {
                    $this->syncBasketProSubscription($service);
                } catch (BasketProException $e) {
                    Log::error("BasketPro: el servicio {$service->id} se renovó aquí pero no allá ({$e->errorCode}): {$e->getMessage()}");
                }
            }
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

    /** @return Collection<int, Service> */
    public function getBasketProServices(): Collection
    {
        return Service::where('type', ServiceTypeEnum::BASKETPRO_SUBSCRIPTION->value)
            ->whereNotNull('basketpro_tenant_code')
            ->get();
    }

    /**
     * Le manda a BasketPro la foto actual del servicio: plan, vigencia y estado.
     *
     * Fechas absolutas, nunca "súmale un mes": repetirlo no cambia nada, por eso la
     * sincronización diaria lo puede llamar sin miedo.
     *
     * @throws BasketProException
     */
    public function syncBasketProSubscription(Service $service): void
    {
        $this->basketPro->updateSubscription($service->basketpro_tenant_code, array_filter([
            'plan_code' => $service->basketpro_plan_code,
            'valid_until' => $service->expiration_date->toDateString(),
            // Vencido aquí es "atrasado" allá, no una baja: BasketPro deja la cuenta en
            // solo lectura, nunca fuera.
            'status' => match ($service->status) {
                ServiceStatusEnum::ACTIVE => 'ACTIVE',
                ServiceStatusEnum::EXPIRED => 'PAST_DUE',
                ServiceStatusEnum::CANCELLED => 'CANCELLED',
            },
        ], fn ($value) => $value !== null));
    }

    /** Un rechazo de BasketPro llega al admin como error del formulario y deshace la transacción. */
    private function callBasketPro(callable $call): void
    {
        try {
            $call();
        } catch (BasketProException $e) {
            throw ValidationException::withMessages(['basketpro' => "BasketPro {$e->getMessage()}"]);
        }
    }
}
