<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\NotificationLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationLog>
 */
class NotificationLogFactory extends Factory
{
    protected $model = NotificationLog::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'service_id' => null,
            'type' => 'whatsapp_reminder',
            'message_body' => 'Aviso de vencimiento enviado.',
            'sent_at' => now(),
        ];
    }
}
