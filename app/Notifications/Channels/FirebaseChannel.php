<?php

namespace App\Notifications\Channels;

use App\Notifications\PaymentReceivedNotification;
use App\Services\FirebaseService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Messaging\NotFound;

class FirebaseChannel
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    public function send($notifiable, Notification $notification)
    {
        $deviceToken = $notifiable->fcm_token;

        if (! $deviceToken) {
            return;
        }

        /** @var PaymentReceivedNotification $notification */
        $data = $notification->toFirebase($notifiable);

        try {
            return $this->firebaseService->sendPushNotification(
                $deviceToken,
                $data['title'],
                $data['body'],
                $data['extra_data'] ?? [],
                $data['link'] ?? null
            );
        } catch (NotFound $e) {
            // Firebase confirma que ese token murio. Si lo dejamos en la base,
            // cada pago volveria a intentar notificar a un dispositivo que ya no
            // existe: fallaria siempre y en silencio.
            //
            // Al borrarlo, el propio flujo se recupera solo: la aplicacion
            // registra un token nuevo en el siguiente inicio de sesion.
            $this->forgetDeadToken($notifiable);

            return null;
        }
    }

    private function forgetDeadToken($notifiable): void
    {
        if (! method_exists($notifiable, 'forceFill')) {
            return;
        }

        $notifiable->forceFill(['fcm_token' => null])->save();

        Log::info(sprintf(
            'Token de Firebase eliminado de %s #%s: el dispositivo ya no esta registrado.',
            class_basename($notifiable),
            $notifiable->getKey()
        ));
    }
}
