<?php

namespace Database\Factories;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceToken>
 */
class DeviceTokenFactory extends Factory
{
    protected $model = DeviceToken::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token' => fake()->unique()->regexify('[A-Za-z0-9_-]{22}:APA91b[A-Za-z0-9_-]{60}'),
            'platform' => 'web',
            'last_used_at' => now(),
        ];
    }
}
