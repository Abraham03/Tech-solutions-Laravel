<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class FirebaseService
{
    protected ?Messaging $messaging = null;

    /**
     * El cliente se construye al usarse, no al inyectarse.
     *
     * Mismo motivo que en StripeService: artisan instancia los comandos
     * registrados para leer sus firmas, y firebase_credentials.json esta en
     * .gitignore, asi que no existe en CI ni en un clon limpio. Construir la
     * Factory en el constructor haria que cualquier comando de artisan
     * reventara en esos entornos.
     */
    protected function messaging(): Messaging
    {
        if ($this->messaging === null) {
            $path = base_path((string) config('services.firebase.credentials', 'firebase_credentials.json'));

            if (! is_file($path)) {
                throw new \RuntimeException("Falta el archivo de credenciales de Firebase en {$path}.");
            }

            $this->messaging = (new Factory)->withServiceAccount($path)->createMessaging();
        }

        return $this->messaging;
    }

    /**
     * Envía una notificación a un dispositivo específico mediante su Token.
     *
     * @param  string|null  $link  URL que abre el clic en navegadores web. Los
     *                             clientes nativos ignoran este bloque y usan
     *                             en su lugar el click_action de $data.
     */
    public function sendPushNotification(string $deviceToken, string $title, string $body, array $data = [], ?string $link = null)
    {
        try {
            $payload = [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
            ];

            // En web, FCM no usa data.click_action para abrir una pantalla:
            // el destino del clic se declara en webpush.fcm_options.link.
            if (filled($link)) {
                $payload['webpush'] = [
                    'fcm_options' => ['link' => $link],
                ];
            }

            return $this->messaging()->send(CloudMessage::fromArray($payload));
        } catch (NotFound $e) {
            // "Device unregistered": el token ya no sirve. Pasa cuando se
            // desinstala la app, se limpian los datos del sitio o se reemplaza
            // el service worker, que invalida la suscripcion push anterior.
            //
            // Se propaga en vez de tragarselo: quien llama debe borrar ese
            // token, o seguiriamos notificando a un dispositivo inexistente en
            // cada pago, indefinidamente y sin que nadie se entere.
            Log::warning('Token de Firebase ya no registrado: '.$e->getMessage());

            throw $e;
        } catch (\Exception $e) {
            Log::error('Error enviando notificación Firebase: '.$e->getMessage());

            return false;
        }
    }
}
