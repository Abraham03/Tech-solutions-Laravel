<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\DeviceToken;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Channels\FirebaseChannel;
use App\Notifications\PaymentReceivedNotification;
use App\Services\FirebaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Mockery;
use Tests\TestCase;

/**
 * Firebase avisa con "Device unregistered" cuando un token murio: el navegador
 * limpio sus datos, se desinstalo la PWA o se reemplazo el service worker, que
 * invalida la suscripcion push anterior.
 *
 * Antes solo se anotaba en el log, asi que cada pago volvia a intentar notificar
 * al mismo dispositivo inexistente, fallando siempre y en silencio.
 */
class DeadFcmTokenTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => RoleEnum::ADMIN->value]);
    }

    private function enviar(User $user): void
    {
        app(FirebaseChannel::class)->send(
            $user,
            new PaymentReceivedNotification(Payment::factory()->create())
        );
    }

    public function test_a_token_rejected_by_firebase_is_removed(): void
    {
        $mock = Mockery::mock(FirebaseService::class);
        $mock->shouldReceive('sendPushNotification')
            ->andThrow(NotFound::becauseTokenNotFound('muerto'));
        $this->instance(FirebaseService::class, $mock);

        $admin = $this->admin();
        DeviceToken::factory()->for($admin)->create(['token' => 'muerto']);

        $this->enviar($admin);

        $this->assertDatabaseMissing('device_tokens', ['token' => 'muerto']);
    }

    /**
     * LA RAZON DE ENVIAR UNO POR UNO: que el celular haya caducado no puede
     * impedir que el aviso llegue al escritorio.
     */
    public function test_a_dead_device_does_not_block_the_healthy_one(): void
    {
        $mock = Mockery::mock(FirebaseService::class);
        $mock->shouldReceive('sendPushNotification')
            ->with('muerto', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
            ->andThrow(NotFound::becauseTokenNotFound('muerto'));
        $mock->shouldReceive('sendPushNotification')
            ->with('vivo', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
            ->once()
            ->andReturn('ok');
        $this->instance(FirebaseService::class, $mock);

        $admin = $this->admin();
        DeviceToken::factory()->for($admin)->create(['token' => 'muerto']);
        DeviceToken::factory()->for($admin)->create(['token' => 'vivo']);

        $this->enviar($admin);

        $this->assertDatabaseMissing('device_tokens', ['token' => 'muerto']);
        $this->assertDatabaseHas('device_tokens', ['token' => 'vivo']);
    }

    /**
     * Un token muerto no puede tumbar el webhook: el pago ya se guardo y a
     * Stripe hay que responderle que todo salio bien.
     */
    public function test_a_dead_token_does_not_raise_an_exception(): void
    {
        $mock = Mockery::mock(FirebaseService::class);
        $mock->shouldReceive('sendPushNotification')
            ->andThrow(NotFound::becauseTokenNotFound('muerto'));
        $this->instance(FirebaseService::class, $mock);

        $admin = $this->admin();
        DeviceToken::factory()->for($admin)->create(['token' => 'muerto']);

        $this->enviar($admin);

        $this->assertTrue(true, 'El canal no debe propagar la excepcion.');
    }

    public function test_a_user_without_devices_never_calls_firebase(): void
    {
        $mock = Mockery::mock(FirebaseService::class);
        $mock->shouldNotReceive('sendPushNotification');
        $this->instance(FirebaseService::class, $mock);

        $this->enviar($this->admin());

        $this->assertTrue(true, 'Sin dispositivos no se llama a Firebase.');
    }
}
