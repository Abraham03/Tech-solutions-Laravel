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
        $this->token = config('services.meta.whatsapp.token');
        $phoneId = config('services.meta.whatsapp.phone_id');
        $version = config('services.meta.whatsapp.version');
        
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