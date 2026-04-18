<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\InfrastructureService;
use App\Services\StripeService;
use App\Notifications\ServiceExpiringNotification;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckExpiringServices extends Command
{
    protected $signature = 'services:check-expiring';
    protected $description = 'Verifica servicios por vencer y envía recordatorio de WhatsApp con link de Stripe';

    protected $infrastructureService;
    protected $stripeService;

    // Inyectamos ambos servicios siguiendo SOLID
    public function __construct(InfrastructureService $infrastructureService, StripeService $stripeService)
    {
        parent::__construct();
        $this->infrastructureService = $infrastructureService;
        $this->stripeService = $stripeService;
    }

    public function handle()
    {
        $this->info('Iniciando escaneo de infraestructura de Tech Solutions...');

        // Buscamos servicios que venzan en los próximos 7 días
        $expiringServices = $this->infrastructureService->getExpiringServices(7);

        if ($expiringServices->isEmpty()) {
            $this->info('Todo en orden. No hay servicios por vencer pronto.');
            return Command::SUCCESS;
        }

        foreach ($expiringServices as $service) {
            $client = $service->project->client;

            if (!$client) continue;

            try {
                // 1. Generamos el link de pago automáticamente para este servicio específico
                $session = $this->stripeService->createCheckoutSession([
                    'client_id'    => $client->id,
                    'project_id'   => $service->project_id,
                    'service_id'   => $service->id,
                    'amount'       => $service->price_mxn,
                    'description'  => "Renovación: {$service->name} ({$service->type->value})",
                    'payment_type' => 'renewal' // Asegúrate de tener este tipo en tu lógica de pagos
                ]);

                // 2. Disparamos la notificación pasando el servicio y el link generado
                $client->notify(new ServiceExpiringNotification($service, $session->url));

                $this->info("Notificación enviada a {$client->name} para el servicio {$service->name}");

            } catch (\Exception $e) {
                Log::error("Error procesando vencimiento para servicio {$service->id}: " . $e->getMessage());
                $this->error("No se pudo procesar el servicio {$service->name}");
            }
        }

        $this->info('Escaneo y notificaciones completadas.');
        return Command::SUCCESS;
    }
}