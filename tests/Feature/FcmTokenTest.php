<?php

namespace Tests\Feature;

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

    public function test_it_stores_the_token_on_the_authenticated_user(): void
    {
        $user = User::factory()->create(['fcm_token' => null]);
        Passport::actingAs($user);

        $this->postJson(self::URL, ['fcm_token' => 'token-del-navegador'])
            ->assertOk();

        $this->assertSame('token-del-navegador', $user->fresh()->fcm_token);
    }

    /**
     * FCM rota el token sin avisar, asi que el endpoint tiene que sobrescribir
     * el anterior en vez de conservarlo.
     */
    public function test_it_replaces_a_previously_registered_token(): void
    {
        $user = User::factory()->create(['fcm_token' => 'token-viejo']);
        Passport::actingAs($user);

        $this->postJson(self::URL, ['fcm_token' => 'token-nuevo'])->assertOk();

        $this->assertSame('token-nuevo', $user->fresh()->fcm_token);
    }

    /**
     * El token se guarda siempre en el usuario de la sesion: mandar un user_id
     * en el cuerpo no debe permitir escribir en la cuenta de otro.
     */
    public function test_it_ignores_a_user_id_sent_by_the_client(): void
    {
        $victima = User::factory()->create(['fcm_token' => null]);
        $atacante = User::factory()->create(['fcm_token' => null]);
        Passport::actingAs($atacante);

        $this->postJson(self::URL, [
            'fcm_token' => 'token-del-atacante',
            'user_id' => $victima->id,
        ])->assertOk();

        $this->assertNull($victima->fresh()->fcm_token);
        $this->assertSame('token-del-atacante', $atacante->fresh()->fcm_token);
    }

    /**
     * Al cerrar sesion el token del dispositivo se borra: si no, los avisos de
     * pagos seguirian llegando a un aparato donde alguien ya salio.
     */
    public function test_logging_out_clears_the_device_token(): void
    {
        $user = User::factory()->create(['fcm_token' => 'token-del-navegador']);
        Passport::actingAs($user);

        $this->postJson('/api/logout')->assertOk();

        $this->assertNull($user->fresh()->fcm_token);
    }

    public function test_it_rejects_a_missing_token(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->postJson(self::URL, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('fcm_token');
    }

    /**
     * La columna fcm_token admite 255 caracteres. Sin esta regla, un token mas
     * largo se truncaria en la base y las notificaciones dejarian de llegar sin
     * que nada lo indicara.
     */
    public function test_it_rejects_a_token_longer_than_the_column(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->postJson(self::URL, ['fcm_token' => str_repeat('a', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('fcm_token');
    }
}
