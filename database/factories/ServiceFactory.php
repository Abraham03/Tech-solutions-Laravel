<?php

namespace Database\Factories;

use App\Enums\ServiceStatusEnum;
use App\Enums\ServiceTypeEnum;
use App\Models\Project;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'type' => ServiceTypeEnum::DOMAIN->value,
            'provider' => 'Hostinger',
            'name' => 'Domain .management',
            'cost_mxn' => 300.00,
            'price_mxn' => 560.00,
            'billing_cycle' => 'annually',
            'expiration_date' => now()->addDays(5)->toDateString(),
            'status' => ServiceStatusEnum::ACTIVE->value,
        ];
    }

    /**
     * Suscripcion de BasketPro ya ligada a su cuenta.
     */
    public function basketpro(string $tenantCode = 'leones'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ServiceTypeEnum::BASKETPRO_SUBSCRIPTION->value,
            'provider' => 'TechSolutions',
            'name' => "BasketPro {$tenantCode}",
            'billing_cycle' => 'monthly',
            'basketpro_tenant_code' => $tenantCode,
            'basketpro_plan_code' => 'starter',
        ]);
    }

    /**
     * Servicio vencido hace $days dias, todavia marcado como activo.
     */
    public function overdue(int $days = 60): static
    {
        return $this->state(fn (array $attributes) => [
            'expiration_date' => now()->subDays($days)->toDateString(),
            'status' => ServiceStatusEnum::ACTIVE->value,
        ]);
    }
}
