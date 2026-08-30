<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Enums\ServiceStatusEnum;
use App\Models\Client;
use App\Models\NotificationLog;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * El dashboard servia todas sus listas en una sola respuesta, con recortes
 * fijos (5 proyectos, 20 notificaciones). Ahora cada pestana pide su pagina.
 */
class DashboardPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::actingAs(User::factory()->create(['role' => RoleEnum::ADMIN->value]));
    }

    public function test_recent_projects_are_paginated(): void
    {
        Project::factory()->count(25)->create();

        $respuesta = $this->getJson('/api/admin/dashboard/recent-projects?per_page=10')->assertOk();

        $this->assertCount(10, $respuesta->json('data'));
        $this->assertSame(25, $respuesta->json('meta.total'));
    }

    /**
     * El recorte de 5 del resumen ya no limita la pestana: pedir la pagina 2
     * devuelve proyectos que antes eran inalcanzables desde el dashboard.
     */
    public function test_the_second_page_goes_beyond_the_old_limit_of_five(): void
    {
        Project::factory()->count(25)->create();

        // per_page=10 (valor de la lista blanca): la pagina 2 trae proyectos que
        // el recorte de 5 del resumen dejaba fuera del alcance del dashboard.
        $respuesta = $this->getJson('/api/admin/dashboard/recent-projects?per_page=10&page=2')->assertOk();

        $this->assertCount(10, $respuesta->json('data'));
        $this->assertSame(2, $respuesta->json('meta.current_page'));
    }

    public function test_client_ltv_is_paginated(): void
    {
        Client::factory()->count(18)->create();

        $respuesta = $this->getJson('/api/admin/dashboard/client-ltv?per_page=10')->assertOk();

        $this->assertCount(10, $respuesta->json('data'));
        $this->assertSame(18, $respuesta->json('meta.total'));
    }

    public function test_expiring_services_are_paginated(): void
    {
        Service::factory()->count(12)->create([
            'status' => ServiceStatusEnum::ACTIVE->value,
            'expiration_date' => now()->addDays(10)->toDateString(),
        ]);

        $respuesta = $this->getJson('/api/admin/dashboard/expiring-services?per_page=10')->assertOk();

        $this->assertCount(10, $respuesta->json('data'));
        $this->assertSame(12, $respuesta->json('meta.total'));
    }

    /**
     * La ventana en dias se acota para que nadie pida un rango absurdo por URL.
     */
    public function test_the_day_window_is_capped(): void
    {
        Service::factory()->create([
            'status' => ServiceStatusEnum::ACTIVE->value,
            'expiration_date' => now()->addDays(200)->toDateString(),
        ]);

        // 30 dias por defecto: el servicio de 200 dias queda fuera.
        $this->getJson('/api/admin/dashboard/expiring-services')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        // Ampliando la ventana si aparece.
        $this->getJson('/api/admin/dashboard/expiring-services?days=365')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        // Un valor desmedido se recorta a 365 y no revienta.
        $this->getJson('/api/admin/dashboard/expiring-services?days=99999')->assertOk();
    }

    public function test_service_margins_are_paginated(): void
    {
        Service::factory()->count(14)->create(['status' => ServiceStatusEnum::ACTIVE->value]);

        $respuesta = $this->getJson('/api/admin/dashboard/service-margins?per_page=10')->assertOk();

        $this->assertCount(10, $respuesta->json('data'));
        $this->assertSame(14, $respuesta->json('meta.total'));
    }

    public function test_notifications_are_paginated(): void
    {
        NotificationLog::factory()->count(30)->create();

        $respuesta = $this->getJson('/api/admin/dashboard/notifications?per_page=10')->assertOk();

        $this->assertCount(10, $respuesta->json('data'));
        $this->assertSame(30, $respuesta->json('meta.total'));
    }

    /**
     * El filtro por canal se resuelve en la base. Filtrarlo en el navegador solo
     * miraria la pagina cargada, que es el fallo que arrastraban las tablas.
     */
    public function test_notifications_can_be_filtered_by_channel(): void
    {
        NotificationLog::factory()->count(8)->create(['type' => 'whatsapp_reminder']);
        NotificationLog::factory()->count(3)->create(['type' => 'push_alert']);

        $this->getJson('/api/admin/dashboard/notifications?type=push_alert')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    public function test_an_unknown_channel_is_ignored_instead_of_failing(): void
    {
        NotificationLog::factory()->count(4)->create();

        $this->getJson('/api/admin/dashboard/notifications?type=canal_inventado')
            ->assertOk()
            ->assertJsonPath('meta.total', 4);
    }

    /**
     * El resumen debe seguir funcionando igual: es lo que pinta la pantalla
     * mientras cada pestana pide su pagina.
     */
    public function test_the_summary_still_works(): void
    {
        Project::factory()->count(3)->create();

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure(['metrics', 'recentProjects', 'expiringServices']);
    }

    public function test_these_endpoints_require_authentication(): void
    {
        // Sin sesion no se puede mirar la informacion financiera del negocio.
        app('auth')->guard('api')->forgetUser();

        $this->postJson('/api/logout');

        foreach (['recent-projects', 'client-ltv', 'expiring-services', 'service-margins', 'notifications'] as $ruta) {
            $this->getJson("/api/admin/dashboard/{$ruta}", ['Authorization' => 'Bearer invalido'])
                ->assertStatus(401);
        }
    }
}
