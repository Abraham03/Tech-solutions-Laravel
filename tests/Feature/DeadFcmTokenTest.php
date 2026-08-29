<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
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

    private function fakeFirebaseThatRejectsTheToken(): void
    {
        $mock = Mockery::mock(FirebaseService::class);
        $mock->shouldReceive('sendPushNotification')
            ->andThrow(NotFound::becauseTokenNotFound('token-que-ya-no-sirve'));

        $this->instance(FirebaseService::class, $mock);
    }

    public function test_a_token_rejected_by_firebase_is_removed_from_the_user(): void
    {
        $this->fakeFirebaseThatRejectsTheToken();

        $admin = User::factory()->create([
            'role' => RoleEnum::ADMIN->value,
            'fcm_token' => 'token-que-ya-no-sirve',
        ]);
        $payment = Payment::factory()->create();

        $canal = app(FirebaseChannel::class);
        $canal->send($admin, new PaymentReceivedNotification($payment));

        $this->assertNull(
            $admin->fresh()->fcm_token,
            'El token muerto debe borrarse para que el sistema deje de intentarlo.'
        );
    }

    /**
     * Un token muerto no puede tumbar el webhook: el pago ya se guardo y a
     * Stripe hay que responderle que todo salio bien.
     */
    public function test_a_dead_token_does_not_raise_an_exception(): void
    {
        $this->fakeFirebaseThatRejectsTheToken();

        $admin = User::factory()->create([
            'role' => RoleEnum::ADMIN->value,
            'fcm_token' => 'token-que-ya-no-sirve',
        ]);
        $payment = Payment::factory()->create();

        $canal = app(FirebaseChannel::class);

        $this->assertNull($canal->send($admin, new PaymentReceivedNotification($payment)));
    }

    public function test_a_user_without_token_is_skipped_without_touching_firebase(): void
    {
        // Si el canal llamara a Firebase, Mockery fallaria: no hay expectativa.
        $mock = Mockery::mock(FirebaseService::class);
        $mock->shouldNotReceive('sendPushNotification');
        $this->instance(FirebaseService::class, $mock);

        $admin = User::factory()->create([
            'role' => RoleEnum::ADMIN->value,
            'fcm_token' => null,
        ]);
        $payment = Payment::factory()->create();

        $canal = app(FirebaseChannel::class);

        $this->assertNull($canal->send($admin, new PaymentReceivedNotification($payment)));
    }
}
