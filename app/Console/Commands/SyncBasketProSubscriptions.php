<?php

namespace App\Console\Commands;

use App\Exceptions\BasketProException;
use App\Services\InfrastructureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncBasketProSubscriptions extends Command
{
    protected $signature = 'basketpro:sync-subscriptions';

    protected $description = 'Le manda a BasketPro el plan, la vigencia y el estado de cada servicio de BasketPro';

    public function handle(InfrastructureService $infrastructureService): int
    {
        // Red de seguridad: si una renovación no le llegó a BasketPro (caído, timeout), aquí
        // se corrige sola en menos de un día. Repetirlo no cambia nada: las fechas son absolutas.
        $failed = 0;

        foreach ($infrastructureService->getBasketProServices() as $service) {
            try {
                $infrastructureService->syncBasketProSubscription($service);
                $this->line("Sincronizada la cuenta {$service->basketpro_tenant_code}");
            } catch (BasketProException $e) {
                $failed++;
                Log::error("BasketPro: no se pudo sincronizar la cuenta {$service->basketpro_tenant_code} ({$e->errorCode}): {$e->getMessage()}");
                $this->error("Falló la cuenta {$service->basketpro_tenant_code}: BasketPro {$e->getMessage()}");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
