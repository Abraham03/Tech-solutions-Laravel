<?php

namespace Tests\Feature;

use App\Enums\ServiceStatusEnum;
use App\Models\NotificationLog;
use App\Models\Project;
use App\Models\Service;
use App\Notifications\ServiceExpiringNotification;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Stripe\Checkout\Session;
use Tests\TestCase;

class CheckExpiringServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->fakeStripe();
    }

    /**
     * Sustituye StripeService por un doble: los tests no deben crear sesiones reales.
     * Devuelve el mock para poder contar cuantas veces se le pidio un link de cobro.
     */
    private function fakeStripe(): Mockery\MockInterface
    {
        // createCheckoutSession declara el tipo de retorno, asi que el doble
        // tiene que devolver una Session de verdad, no un stdClass.
        $session = Session::constructFrom([
            'id' => 'cs_test_fake',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_fake',
        ]);

        $mock = Mockery::mock(StripeService::class);
        $mock->shouldReceive('createCheckoutSession')->andReturn($session);

        $this->instance(StripeService::class, $mock);

        return $mock;
    }

    public function test_it_notifies_a_service_about_to_expire(): void
    {
        $project = Project::factory()->create();
        Service::factory()->create([
            'project_id' => $project->id,
            'expiration_date' => now()->addDays(3)->toDateString(),
        ]);

        $this->artisan('services:check-expiring')->assertSuccessful();

        Notification::assertSentTimes(ServiceExpiringNotification::class, 1);
        $this->assertDatabaseCount('notification_logs', 1);
    }

    /**
     * EL BUG DEL SPAM: antes, cada corrida del cron mandaba otro WhatsApp y generaba
     * otro link de Stripe para el mismo servicio, dia tras dia.
     */
    public function test_a_second_run_does_not_notify_again_within_the_cooldown(): void
    {
        $project = Project::factory()->create();
        Service::factory()->create([
            'project_id' => $project->id,
            'expiration_date' => now()->addDays(3)->toDateString(),
        ]);

        $this->artisan('services:check-expiring')->assertSuccessful();
        $this->artisan('services:check-expiring')->assertSuccessful();

        // Una sola notificacion y un solo registro, no dos.
        Notification::assertSentTimes(ServiceExpiringNotification::class, 1);
        $this->assertDatabaseCount('notification_logs', 1);
    }

    public function test_it_notifies_again_once_the_cooldown_has_passed(): void
    {
        $project = Project::factory()->create();
        $service = Service::factory()->create([
            'project_id' => $project->id,
            'expiration_date' => now()->addDays(3)->toDateString(),
        ]);

        // Un aviso de hace 8 dias ya quedo fuera de la ventana de enfriamiento.
        NotificationLog::factory()->create([
            'client_id' => $project->client_id,
            'service_id' => $service->id,
            'type' => 'whatsapp_reminder',
            'sent_at' => now()->subDays(8),
        ]);

        $this->artisan('services:check-expiring')->assertSuccessful();

        Notification::assertSentTimes(ServiceExpiringNotification::class, 1);
    }

    /**
     * Lo que lleva mucho tiempo vencido se da de baja en vez de acumular
     * recordatorios semanales para siempre.
     */
    public function test_long_overdue_services_are_marked_expired_and_not_notified(): void
    {
        $project = Project::factory()->create();
        $service = Service::factory()->overdue(60)->create(['project_id' => $project->id]);

        $this->artisan('services:check-expiring')->assertSuccessful();

        $this->assertSame(ServiceStatusEnum::EXPIRED, $service->fresh()->status);
        Notification::assertNothingSent();
        $this->assertDatabaseCount('notification_logs', 0);
    }

    /**
     * Un servicio recien vencido sigue dentro del periodo de gracia: se le insiste.
     */
    public function test_a_recently_expired_service_is_still_notified(): void
    {
        $project = Project::factory()->create();
        $service = Service::factory()->overdue(5)->create(['project_id' => $project->id]);

        $this->artisan('services:check-expiring')->assertSuccessful();

        $this->assertSame(ServiceStatusEnum::ACTIVE, $service->fresh()->status);
        Notification::assertSentTimes(ServiceExpiringNotification::class, 1);
    }
}
