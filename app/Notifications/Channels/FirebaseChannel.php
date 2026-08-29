<?php

namespace App\Notifications\Channels;

use App\Notifications\PaymentReceivedNotification;
use App\Services\FirebaseService;
use Illuminate\Notifications\Notification;

class FirebaseChannel
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    public function send($notifiable, Notification $notification)
    {
        // Buscamos el token del dispositivo en el modelo (User o Client)
        // En la Fase 6, guardaremos este token en la base de datos
        $deviceToken = $notifiable->fcm_token;

        if (! $deviceToken) {
            return;
        }

        /** @var PaymentReceivedNotification $notification */
        $data = $notification->toFirebase($notifiable);

        return $this->firebaseService->sendPushNotification(
            $deviceToken,
            $data['title'],
            $data['body'],
            $data['extra_data'] ?? [],
            $data['link'] ?? null
        );
    }
}
