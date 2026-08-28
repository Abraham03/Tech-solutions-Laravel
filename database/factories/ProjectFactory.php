<?php

namespace Database\Factories;

use App\Enums\ProjectStatusEnum;
use App\Enums\ProjectTypeEnum;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => fake()->catchPhrase(),
            'type' => ProjectTypeEnum::WEBSITE->value,
            'total_price' => 25000.00,
            'currency' => 'MXN',
            'status' => ProjectStatusEnum::DEVELOPMENT->value,
        ];
    }

    /**
     * Proyecto ya entregado y sin saldo pendiente.
     * Es el escenario que rompia las renovaciones.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatusEnum::COMPLETED->value,
        ]);
    }
}
