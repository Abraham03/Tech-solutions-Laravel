<?php

namespace App\Notifications\Channels;

use App\Models\DeviceToken;
use App\Notifications\PaymentReceivedNotification;
use App\Services\FirebaseService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Messaging\NotFound;

class FirebaseChannel
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    public function send($notifiable, Notification $notification): void
    {
        $devices = $this->devicesFor($notifiable);

        if ($devices->isEmpty()) {
            return;
        }

        /** @var PaymentReceivedNotification $notification */
        $data = $notification->toFirebase($notifiable);

        foreach ($devices as $device) {
            $this->sendToDevice($device, $data);
        }
    }

    /**
     * Cada dispositivo se envia por separado y se aisla: que el token del
     * celular haya muerto no puede impedir que llegue al escritorio.
     */
    private function sendToDevice(DeviceToken $device, array $data): void
    {
        try {
            $this->firebaseService->sendPushNotification(
                $device->token,
                $data['title'],
                $data['body'],
                $data['extra_data'] ?? [],
                $data['link'] ?? null
            );
        } catch (NotFound $e) {
            // Firebase confirma que este token murio: el navegador limpio sus
            // datos, se desinstalo la PWA o se reemplazo el service worker.
            // Se borra SOLO este dispositivo; los demas siguen recibiendo.
            $device->delete();

            Log::info("Dispositivo #{$device->id} del usuario #{$device->user_id} eliminado: ya no esta registrado en Firebase.");
        }
    }

    private function devicesFor($notifiable): Collection
    {
        if (! method_exists($notifiable, 'deviceTokens')) {
            return collect();
        }

        return $notifiable->deviceTokens()->get();
    }
}
