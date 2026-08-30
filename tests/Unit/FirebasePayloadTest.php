<?php

namespace Tests\Unit;

use App\Services\FirebaseService;
use Tests\TestCase;

/**
 * El payload se enviaba con un bloque 'notification'. Eso hacia que el SDK de
 * Firebase pintara la notificacion por su cuenta y que ADEMAS se disparara
 * onBackgroundMessage en el service worker, que la pintaba otra vez: el usuario
 * recibia el mismo aviso duplicado, cada copia con un icono distinto.
 */
class FirebasePayloadTest extends TestCase
{
    private function payload(array $data = [], ?string $link = null): array
    {
        return (new FirebaseService)->buildPayload(
            'token-del-dispositivo',
            'Pago recibido',
            'Se registro un abono de $750.00 MXN.',
            $data,
            $link
        );
    }

    public function test_the_payload_has_no_notification_block(): void
    {
        $this->assertArrayNotHasKey(
            'notification',
            $this->payload(),
            'Con bloque notification, el SDK y el service worker muestran el aviso por duplicado.'
        );
    }

    public function test_the_title_and_body_travel_inside_data(): void
    {
        $payload = $this->payload();

        // El service worker los lee de aqui para poder pintarlos el mismo.
        $this->assertSame('Pago recibido', $payload['data']['title']);
        $this->assertSame('Se registro un abono de $750.00 MXN.', $payload['data']['body']);
        $this->assertSame('token-del-dispositivo', $payload['token']);
    }

    public function test_the_link_is_included_when_there_is_one(): void
    {
        $payload = $this->payload([], 'https://techsolutions.management/admin/payments');

        $this->assertSame('https://techsolutions.management/admin/payments', $payload['data']['link']);
    }

    public function test_without_a_link_no_empty_key_is_sent(): void
    {
        $this->assertArrayNotHasKey('link', $this->payload()['data']);
    }

    /**
     * FCM rechaza el mensaje si algun valor de 'data' no es una cadena.
     */
    public function test_every_data_value_is_cast_to_string(): void
    {
        $payload = $this->payload(['payment_id' => 15, 'reintento' => true]);

        foreach ($payload['data'] as $clave => $valor) {
            $this->assertIsString($valor, "El valor de '{$clave}' debe ser una cadena.");
        }

        $this->assertSame('15', $payload['data']['payment_id']);
    }
}
