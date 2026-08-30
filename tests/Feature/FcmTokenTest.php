<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FcmTokenTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/me/fcm-token';

    public function test_it_requires_authentication(): void
    {
        $this->postJson(self::URL, ['fcm_token' => 'token-cualquiera'])
            ->assertStatus(401);
    }

    public function test_it_registers_the_device_of_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->postJson(self::URL, ['fcm_token' => 'token-del-navegador', 'platform' => 'web'])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'token-del-navegador',
            'platform' => 'web',
        ]);
    }

    /**
     * EL MOTIVO DE ESTA TABLA: con una sola columna en users, registrar el
     * celular borraba el token del escritorio y ese dejaba de recibir avisos.
     */
    public function test_registering_a_second_device_keeps_the_first_one(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->postJson(self::URL, ['fcm_token' => 'token-escritorio', 'platform' => 'web'])->assertOk();
        $this->postJson(self::URL, ['fcm_token' => 'token-celular', 'platform' => 'android'])->assertOk();

        $this->assertSame(2, $user->deviceTokens()->count());
        $this->assertDatabaseHas('device_tokens', ['token' => 'token-escritorio']);
        $this->assertDatabaseHas('device_tokens', ['token' => 'token-celular']);
    }

    /**
     * FCM puede devolver el mismo token en cada arranque; no debe duplicarse.
     */
    public function test_registering_the_same_token_twice_does_not_duplicate_it(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->postJson(self::URL, ['fcm_token' => 'mismo-token'])->assertOk();
        $this->postJson(self::URL, ['fcm_token' => 'mismo-token'])->assertOk();

        $this->assertSame(1, DeviceToken::where('token', 'mismo-token')->count());
    }

    /**
     * Si alguien inicia sesion en un dispositivo que era de otra cuenta, el
     * aparato cambia de dueno: no puede seguir recibiendo los pagos del usuario
     * anterior.
     */
    public function test_a_device_reused_by_another_user_changes_owner(): void
    {
        $primero = User::factory()->create();
        $segundo = User::factory()->create();

        Passport::actingAs($primero);
        $this->postJson(self::URL, ['fcm_token' => 'token-compartido'])->assertOk();

        Passport::actingAs($segundo);
        $this->postJson(self::URL, ['fcm_token' => 'token-compartido'])->assertOk();

        $this->assertSame(0, $primero->deviceTokens()->count());
        $this->assertSame(1, $segundo->deviceTokens()->count());
        $this->assertSame(1, DeviceToken::where('token', 'token-compartido')->count());
    }

    /**
     * Al salir se da de baja SOLO el aparato desde el que se cierra sesion.
     */
    public function test_logging_out_only_removes_the_device_that_signed_out(): void
    {
        $user = User::factory()->create();
        DeviceToken::factory()->for($user)->create(['token' => 'token-escritorio']);
        DeviceToken::factory()->for($user)->create(['token' => 'token-celular']);

        Passport::actingAs($user);
        $this->postJson('/api/logout', ['fcm_token' => 'token-escritorio'])->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'token-escritorio']);
        $this->assertDatabaseHas('device_tokens', ['token' => 'token-celular']);
    }

    /**
     * Sin token en la peticion no se puede saber que aparato dar de baja, y
     * borrarlos todos desconectaria los demas dispositivos del usuario.
     */
    public function test_logging_out_without_a_token_keeps_every_device(): void
    {
        $user = User::factory()->create();
        DeviceToken::factory()->for($user)->count(2)->create();

        Passport::actingAs($user);
        $this->postJson('/api/logout')->assertOk();

        $this->assertSame(2, $user->deviceTokens()->count());
    }

    public function test_it_rejects_a_missing_token(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->postJson(self::URL, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('fcm_token');
    }

    /**
     * La columna admite 255 caracteres. Sin esta regla, un token mas largo se
     * truncaria en la base y las notificaciones dejarian de llegar sin aviso.
     */
    public function test_it_rejects_a_token_longer_than_the_column(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->postJson(self::URL, ['fcm_token' => str_repeat('a', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('fcm_token');
    }
}
