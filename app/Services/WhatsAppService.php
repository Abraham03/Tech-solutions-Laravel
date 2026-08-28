<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $baseUrl;

    protected string $token;

    protected string $phoneId;

    public function __construct()
    {
        // config() y NO env(): env() solo lee el .env mientras la configuracion no
        // este cacheada. En cuanto se ejecuta `php artisan config:cache` devuelve
        // null, y este servicio empezaria a mandar peticiones sin token contra
        // Meta, fallando en silencio en produccion.
        $this->token = (string) config('services.meta.whatsapp.token', '');
        $this->phoneId = (string) config('services.meta.whatsapp.phone_id', '');
        $version = (string) config('services.meta.whatsapp.version', 'v25.0');

        $this->baseUrl = "https://graph.facebook.com/{$version}/{$this->phoneId}/messages";
    }

    /**
     * (FASE 4) Envía notificaciones automáticas usando Plantillas de Meta.
     */
    public function sendTemplate(string $to, string $templateName, array $components = [], string $language = 'es_MX')
    {
        return $this->sendRequest([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $components,
            ],
        ]);
    }

    /**
     * (FUTURO MÓDULO DE CHAT) Envía texto libre.
     * Solo válido dentro de la ventana de 24 hrs de atención al cliente.
     */
    public function sendText(string $to, string $message)
    {
        return $this->sendRequest([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => true,
                'body' => $message,
            ],
        ]);
    }

    /**
     * Método centralizado para peticiones HTTP (DRY).
     */
    protected function sendRequest(array $payload)
    {
        // Sin credenciales, Meta responderia 401 y el error real quedaria enterrado
        // en el cuerpo de la respuesta. Mejor decir exactamente que falta.
        if ($this->token === '' || $this->phoneId === '') {
            Log::error('Falta META_WHATSAPP_TOKEN o META_WHATSAPP_PHONE_ID: no se envio el mensaje de WhatsApp.');

            return false;
        }

        $response = Http::withToken($this->token)->post($this->baseUrl, $payload);

        if ($response->failed()) {
            Log::error('Error en Meta API: '.$response->body());

            return false;
        }

        return $response->json();
    }
}
