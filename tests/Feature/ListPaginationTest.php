<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ListPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function comoAdmin(): void
    {
        Passport::actingAs(User::factory()->create(['role' => RoleEnum::ADMIN->value]));
    }

    public function test_the_list_is_paginated_and_reports_the_total(): void
    {
        $this->comoAdmin();
        Client::factory()->count(20)->create();

        $respuesta = $this->getJson('/api/admin/clients')->assertOk();

        // 15 es el tamano por defecto.
        $this->assertCount(15, $respuesta->json('data.data'));
        $this->assertSame(20, $respuesta->json('data.meta.total'));
        $this->assertSame(1, $respuesta->json('data.meta.current_page'));
    }

    public function test_the_page_parameter_returns_the_remaining_records(): void
    {
        $this->comoAdmin();
        Client::factory()->count(20)->create();

        $respuesta = $this->getJson('/api/admin/clients?page=2')->assertOk();

        $this->assertCount(5, $respuesta->json('data.data'));
        $this->assertSame(2, $respuesta->json('data.meta.current_page'));
    }

    public function test_per_page_changes_the_page_size(): void
    {
        $this->comoAdmin();
        Client::factory()->count(30)->create();

        $respuesta = $this->getJson('/api/admin/clients?per_page=10')->assertOk();

        $this->assertCount(10, $respuesta->json('data.data'));
    }

    /**
     * per_page se valida contra una lista blanca. Un valor enorme no puede
     * arrastrar toda la tabla en una sola peticion.
     */
    public function test_an_abusive_per_page_falls_back_to_the_default(): void
    {
        $this->comoAdmin();
        Client::factory()->count(30)->create();

        foreach (['10000', '-1', 'muchos', '0'] as $valor) {
            $respuesta = $this->getJson("/api/admin/clients?per_page={$valor}")->assertOk();

            $this->assertCount(15, $respuesta->json('data.data'), "per_page={$valor} deberia caer al valor por defecto.");
        }
    }

    /**
     * LO QUE ARREGLA ESTA BUSQUEDA: antes el filtrado ocurria en el navegador y
     * solo miraba los registros de la pagina cargada, asi que buscar algo que
     * estuviera en la pagina 3 no devolvia nada.
     */
    public function test_the_search_looks_at_the_whole_table_not_just_one_page(): void
    {
        $this->comoAdmin();
        Client::factory()->count(30)->create();
        // Se crea al final, asi que queda fuera de la primera pagina por fecha.
        Client::factory()->create(['name' => 'Ferreteria Buscada']);

        $respuesta = $this->getJson('/api/admin/clients?search=Ferreteria')->assertOk();

        $this->assertSame(1, $respuesta->json('data.meta.total'));
        // ClientResource expone 'name' del modelo como 'company_name'.
        $this->assertSame('Ferreteria Buscada', $respuesta->json('data.data.0.company_name'));
    }

    public function test_the_search_also_matches_the_email(): void
    {
        $this->comoAdmin();
        Client::factory()->count(5)->create();
        Client::factory()->create(['email' => 'contacto@panaderia.mx']);

        $respuesta = $this->getJson('/api/admin/clients?search=panaderia')->assertOk();

        $this->assertSame(1, $respuesta->json('data.meta.total'));
    }

    /**
     * Sin withQueryString(), pasar a la pagina 2 perderia el filtro y el usuario
     * veria de pronto la lista completa.
     */
    public function test_pagination_links_keep_the_search_term(): void
    {
        $this->comoAdmin();
        Client::factory()->count(40)->create(['name' => 'Comercial del Norte']);

        $respuesta = $this->getJson('/api/admin/clients?search=Comercial&per_page=10')->assertOk();

        $this->assertStringContainsString('search=Comercial', $respuesta->json('data.links.next'));
        $this->assertStringContainsString('per_page=10', $respuesta->json('data.links.next'));
    }

    public function test_a_search_without_matches_returns_an_empty_page(): void
    {
        $this->comoAdmin();
        Client::factory()->count(10)->create();

        $respuesta = $this->getJson('/api/admin/clients?search=noexistenadaasi')->assertOk();

        $this->assertSame(0, $respuesta->json('data.meta.total'));
        $this->assertCount(0, $respuesta->json('data.data'));
    }

    /**
     * Los cinco modulos deben comportarse igual: misma forma de respuesta y
     * mismos parametros. Usuarios era el unico que devolvia un array plano.
     */
    public function test_every_module_paginates_the_same_way(): void
    {
        $this->comoAdmin();

        Client::factory()->count(3)->create();
        Project::factory()->count(3)->create();
        Service::factory()->count(3)->create();
        Payment::factory()->count(3)->create();
        User::factory()->count(3)->create();

        foreach (['clients', 'projects', 'services', 'payments', 'users'] as $modulo) {
            $respuesta = $this->getJson("/api/admin/{$modulo}?per_page=10")->assertOk();

            $this->assertIsArray($respuesta->json('data.data'), "{$modulo} deberia devolver data.data");
            $this->assertNotNull($respuesta->json('data.meta.total'), "{$modulo} deberia informar el total");
            $this->assertNotNull($respuesta->json('data.meta.current_page'), "{$modulo} deberia informar la pagina actual");
        }
    }

    public function test_searching_a_project_by_its_client_name(): void
    {
        $this->comoAdmin();
        Project::factory()->count(5)->create();

        $cliente = Client::factory()->create(['name' => 'Panaderia La Espiga']);
        Project::factory()->create(['client_id' => $cliente->id, 'name' => 'Sitio institucional']);

        $respuesta = $this->getJson('/api/admin/projects?search=Espiga')->assertOk();

        $this->assertSame(1, $respuesta->json('data.meta.total'));
        $this->assertSame('Sitio institucional', $respuesta->json('data.data.0.name'));
    }
}
