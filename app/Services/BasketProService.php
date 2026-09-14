<?php

namespace App\Services;

use App\Exceptions\BasketProException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Cliente de la API de servicio de BasketPro (/api/v1/service/*).
 *
 * A diferencia de WhatsAppService, un fallo LANZA en vez de devolver false: al dar de
 * alta una cuenta el admin tiene que ver por que no se pudo, y quien llama decide si
 * el fallo detiene la operacion (alta, edicion) o solo se anota (renovacion).
 *
 * Nunca se registra en logs el token ni el cuerpo de la peticion: el alta lleva la
 * contraseña del admin.
 */
class BasketProService
{
    /** @return array<string, mixed> */
    public function createTenant(array $payload): array
    {
        return $this->send('post', 'tenants', $payload);
    }

    /** @return array<string, mixed> */
    public function getTenant(string $code): array
    {
        return $this->send('get', "tenants/{$code}");
    }

    /** @return array<string, mixed> */
    public function updateSubscription(string $code, array $payload): array
    {
        return $this->send('patch', "tenants/{$code}/subscription", $payload);
    }

    /** @return array<string, mixed> */
    private function send(string $method, string $path, array $payload = []): array
    {
        // config() y no env(): env() devuelve null en cuanto se cachea la configuracion.
        $url = rtrim((string) config('services.basketpro.url', ''), '/');
        $token = (string) config('services.basketpro.token', '');

        if ($url === '' || $token === '') {
            throw new BasketProException('no está configurado (faltan BASKETPRO_API_URL o BASKETPRO_SERVICE_TOKEN).', 'not_configured');
        }

        $request = Http::withToken($token)
            ->acceptJson()
            ->timeout((int) config('services.basketpro.timeout', 120));

        $endpoint = "{$url}/api/v1/service/{$path}";

        try {
            $response = match ($method) {
                'get' => $request->get($endpoint),
                'post' => $request->post($endpoint, $payload),
                'patch' => $request->patch($endpoint, $payload),
            };
        } catch (ConnectionException $e) {
            throw new BasketProException('no respondió a tiempo o no se pudo conectar.', 'unreachable');
        }

        if ($response->failed()) {
            throw new BasketProException(
                (string) ($response->json('message') ?? "respondió {$response->status()}."),
                (string) ($response->json('code') ?? "http_{$response->status()}"),
            );
        }

        return (array) $response->json('data', []);
    }
}
