<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Services\InfrastructureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Servicios tipo 'basketpro_subscription': TechSolutions cobra, BasketPro obedece.
 * BasketPro nunca se llama de verdad; se simula con Http::fake().
 */
class BasketProSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://basketpro.test';

    private const PASSWORD = 'Srv4Prueba9qX';

    private const DB_PASSWORD = 'Clave-de-hPanel-9';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.basketpro.url' => self::URL,
            'services.basketpro.token' => 'token-de-prueba',
        ]);

        Passport::actingAs(User::factory()->create(['role' => RoleEnum::ADMIN->value]));
    }

    private function basketProPayload(array $overrides = []): array
    {
        return array_merge([
            'project_id' => Project::factory()->create()->id,
            'type' => 'basketpro_subscription',
            'provider' => 'TechSolutions',
            'name' => 'BasketPro Leones',
            'cost_mxn' => 0,
            'price_mxn' => 800,
            'billing_cycle' => 'monthly',
            'expiration_date' => '2026-10-14',
            'basketpro' => [
                'account_code' => 'leones',
                'database_name' => 'u525153682_leones',
                'database_username' => 'u525153682_leones_user',
                'database_password' => self::DB_PASSWORD,
                'league_code' => 'liga-leones',
                'league_name' => 'Liga Leones',
                'admin_name' => 'Admin Leones',
                'admin_username' => 'admin-leones',
                'admin_password' => self::PASSWORD,
                'plan_code' => 'starter',
            ],
        ], $overrides);
    }

    // ------------------------------------------------------------------ alta

    public function test_creating_a_basketpro_service_creates_the_account(): void
    {
        Http::fake([self::URL.'/*' => Http::response(['data' => ['code' => 'leones']], 201)]);

        $payload = $this->basketProPayload();
        $response = $this->postJson('/api/admin/services', $payload)->assertCreated();

        $service = Service::firstOrFail();
        $this->assertSame('leones', $service->basketpro_tenant_code);
        $this->assertSame('starter', $service->basketpro_plan_code);

        $clientName = Project::findOrFail($payload['project_id'])->client->name;

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === self::URL.'/api/v1/service/tenants'
            && $request->hasHeader('Authorization', 'Bearer token-de-prueba')
            && $request['name'] === $clientName
            && $request['code'] === 'leones'
            && $request['admin_password'] === self::PASSWORD
            && $request['database_username'] === 'u525153682_leones_user'
            && $request['database_password'] === self::DB_PASSWORD
            && $request['valid_until'] === '2026-10-14');

        // Las contraseñas viajan a BasketPro y a ningún otro lado.
        foreach ([self::PASSWORD, self::DB_PASSWORD] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
            $this->assertStringNotContainsString($secret, json_encode($service->getAttributes()));
        }
    }

    public function test_every_validation_reason_from_basketpro_reaches_the_form(): void
    {
        // Laravel resume los rechazos de validación en `message` con un "(and 1 more error)"
        // en inglés; el admin tiene que ver cada motivo, en español.
        Http::fake([self::URL.'/*' => Http::response([
            'message' => 'La contraseña del administrador debe tener al menos un número. (and 1 more error)',
            'errors' => [
                'admin_password' => ['La contraseña del administrador debe tener al menos un número.'],
                'database_password' => ['Falta la contraseña del usuario de la base.'],
            ],
        ], 422)]);

        $this->postJson('/api/admin/services', $this->basketProPayload())
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.basketpro.0',
                'BasketPro La contraseña del administrador debe tener al menos un número. Falta la contraseña del usuario de la base.'
            );

        $this->assertDatabaseCount('services', 0);
    }

    public function test_a_rejected_account_does_not_leave_the_service_behind(): void
    {
        Http::fake([self::URL.'/*' => Http::response([
            'message' => 'El código `leones` ya está tomado.',
            'code' => 'tenant_code_taken',
        ], 422)]);

        $this->postJson('/api/admin/services', $this->basketProPayload())
            ->assertStatus(422)
            ->assertJsonPath('errors.basketpro.0', 'BasketPro El código `leones` ya está tomado.');

        $this->assertDatabaseCount('services', 0);
    }

    public function test_without_configuration_it_refuses_instead_of_creating_half(): void
    {
        config(['services.basketpro.url' => '']);
        Http::fake();

        $this->postJson('/api/admin/services', $this->basketProPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('basketpro');

        $this->assertDatabaseCount('services', 0);
        Http::assertNothingSent();
    }

    public function test_it_requires_the_account_data_for_that_type(): void
    {
        Http::fake();

        $payload = $this->basketProPayload();
        unset($payload['basketpro']);

        $this->postJson('/api/admin/services', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'basketpro.account_code',
                'basketpro.admin_password',
                'basketpro.database_username',
                'basketpro.database_password',
            ]);

        Http::assertNothingSent();
    }

    public function test_other_service_types_never_call_basketpro(): void
    {
        Http::fake();

        $payload = $this->basketProPayload(['type' => 'domain']);
        unset($payload['basketpro']);

        $this->postJson('/api/admin/services', $payload)->assertCreated();

        Http::assertNothingSent();
        $this->assertNull(Service::firstOrFail()->basketpro_tenant_code);
    }

    // --------------------------------------------------------------- edición

    public function test_changing_the_expiration_or_plan_updates_the_subscription(): void
    {
        Http::fake([self::URL.'/*' => Http::response(['data' => []], 200)]);
        $service = Service::factory()->basketpro('leones')->create(['expiration_date' => '2026-10-14']);

        $this->putJson("/api/admin/services/{$service->id}", [
            'expiration_date' => '2026-11-14',
            'basketpro' => ['plan_code' => 'premium'],
        ])->assertOk();

        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
            && $request->url() === self::URL.'/api/v1/service/tenants/leones/subscription'
            && $request['valid_until'] === '2026-11-14'
            && $request['plan_code'] === 'premium'
            && $request['status'] === 'ACTIVE');

        $this->assertSame('premium', $service->fresh()->basketpro_plan_code);
    }

    public function test_a_failed_update_keeps_the_previous_values(): void
    {
        Http::fake([self::URL.'/*' => Http::response(['message' => 'Server Error'], 500)]);
        $service = Service::factory()->basketpro('leones')->create(['expiration_date' => '2026-10-14']);

        $this->putJson("/api/admin/services/{$service->id}", ['expiration_date' => '2026-11-14'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('basketpro');

        $this->assertSame('2026-10-14', $service->fresh()->expiration_date->toDateString());
    }

    public function test_editing_unrelated_fields_does_not_call_basketpro(): void
    {
        Http::fake();
        $service = Service::factory()->basketpro('leones')->create();

        $this->putJson("/api/admin/services/{$service->id}", ['name' => 'BasketPro Leones (liga varonil)'])
            ->assertOk();

        Http::assertNothingSent();
    }

    public function test_a_basketpro_service_cannot_change_type(): void
    {
        Http::fake();
        $service = Service::factory()->basketpro('leones')->create();

        $this->putJson("/api/admin/services/{$service->id}", ['type' => 'domain'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');

        Http::assertNothingSent();
    }

    // ------------------------------------------------------------ renovación

    public function test_a_renewal_pushes_the_new_expiration(): void
    {
        Http::fake([self::URL.'/*' => Http::response(['data' => []], 200)]);
        $service = Service::factory()->basketpro('leones')->create([
            'expiration_date' => now()->addDays(3)->toDateString(),
        ]);

        app(InfrastructureService::class)->renewService($service);

        $expected = now()->addDays(3)->addMonth()->toDateString();

        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
            && $request['valid_until'] === $expected
            && $request['status'] === 'ACTIVE');
    }

    public function test_basketpro_being_down_does_not_undo_a_renewal(): void
    {
        // El webhook de Stripe llama a renewService: si BasketPro está caído, el pago y
        // la renovación se quedan aquí, y la sincronización diaria corrige allá.
        Http::fake([self::URL.'/*' => Http::response(null, 503)]);
        $service = Service::factory()->basketpro('leones')->create([
            'expiration_date' => now()->addDays(3)->toDateString(),
        ]);

        app(InfrastructureService::class)->renewService($service);

        $this->assertSame(
            now()->addDays(3)->addMonth()->toDateString(),
            $service->fresh()->expiration_date->toDateString(),
        );
    }

    // --------------------------------------------------- sincronización diaria

    public function test_the_daily_sync_pushes_every_basketpro_service(): void
    {
        Http::fake([self::URL.'/*' => Http::response(['data' => []], 200)]);
        Service::factory()->basketpro('leones')->create();
        Service::factory()->basketpro('halcones')->create(['status' => 'expired']);
        Service::factory()->create(); // un dominio: no es de BasketPro

        $this->artisan('basketpro:sync-subscriptions')->assertSuccessful();

        Http::assertSentCount(2);
        // Vencido aquí es "atrasado" allá, no una baja.
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/tenants/halcones/subscription')
            && $request['status'] === 'PAST_DUE');
    }

    public function test_the_daily_sync_reports_failures(): void
    {
        Http::fake([self::URL.'/*' => Http::response(['message' => 'Server Error'], 500)]);
        Service::factory()->basketpro('leones')->create();

        $this->artisan('basketpro:sync-subscriptions')->assertFailed();
    }
}
