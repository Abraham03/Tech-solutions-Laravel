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
     * Construye el mensaje que se manda a FCM.
     *
     * SIN bloque 'notification' a proposito. Cuando el payload lo incluye, el
     * SDK de Firebase pinta la notificacion por su cuenta Y ADEMAS dispara
     * onBackgroundMessage en el service worker, que la pinta otra vez: el
     * usuario recibia el mismo aviso duplicado, cada copia con un icono
     * distinto.
     *
     * Enviandolo como data-only, el service worker es el unico que muestra, y
     * ademas conserva el control del icono, del tag y del enlace del clic.
     *
     * Nota para el futuro: un cliente nativo (Flutter) no muestra nada por si
     * solo con data-only. Cuando exista, habra que enviarle a el si el bloque
     * 'notification' y distinguir por plataforma.
     *
     * @return array<string, mixed>
     */
    public function buildPayload(string $deviceToken, string $title, string $body, array $data = [], ?string $link = null): array
    {
        // FCM exige que todos los valores de 'data' sean cadenas.
        $carga = array_map(static fn ($valor) => (string) $valor, $data);

        $carga['title'] = $title;
        $carga['body'] = $body;

        if (filled($link)) {
            $carga['link'] = $link;
        }

        return [
            'token' => $deviceToken,
            'data' => $carga,
        ];
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
            return $this->messaging()->send(
                CloudMessage::fromArray($this->buildPayload($deviceToken, $title, $body, $data, $link))
            );
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
