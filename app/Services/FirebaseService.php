<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class FirebaseService
{
    protected $messaging;

    public function __construct()
    {
        // El archivo debe estar en la raíz del proyecto (WSL)
        $factory = (new Factory)->withServiceAccount(base_path('firebase_credentials.json'));
        $this->messaging = $factory->createMessaging();
    }

    /**
     * Envía una notificación a un dispositivo específico mediante su Token.
     */
    public function sendPushNotification(string $deviceToken, string $title, string $body, array $data = [])
    {
        try {
            // Uso de fromArray: el estándar recomendado y compatible al 100% que Intelephense entiende perfecto
            $message = CloudMessage::fromArray([
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
            ]);

            return $this->messaging->send($message);
        } catch (\Exception $e) {
            Log::error('Error enviando notificación Firebase: '.$e->getMessage());

            return false;
        }
    }
}
