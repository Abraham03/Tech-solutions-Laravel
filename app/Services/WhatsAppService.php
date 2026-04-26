<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $baseUrl;
    protected string $token;

   public function __construct()
    {
        // Leemos directamente las llaves exactas que pusiste en tu .env
        // El '' al final asegura que si no lo encuentra, asigne un texto vacío en lugar de null
        $this->token = env('META_WHATSAPP_TOKEN', config('services.meta.whatsapp.token', ''));
        $phoneId = env('META_WHATSAPP_PHONE_ID', config('services.meta.whatsapp.phone_id', ''));
        $version = env('META_WHATSAPP_VERSION', config('services.meta.whatsapp.version', 'v25.0'));
        
        $this->baseUrl = "https://graph.facebook.com/{$version}/{$phoneId}/messages";
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
                'components' => $components
            ]
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
                'body' => $message
            ]
        ]);
    }

    /**
     * Método centralizado para peticiones HTTP (DRY).
     */
    protected function sendRequest(array $payload)
    {
        $response = Http::withToken($this->token)->post($this->baseUrl, $payload);

        if ($response->failed()) {
            Log::error('Error en Meta API: ' . $response->body());
            return false;
        }

        return $response->json();
    }
}