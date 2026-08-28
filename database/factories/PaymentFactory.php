<?php

namespace Database\Factories;

use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\PaymentTypeEnum;
use App\Models\Client;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'project_id' => null,
            'service_id' => null,
            'amount' => 1000.00,
            'payment_method' => PaymentMethodEnum::TRANSFER->value,
            'payment_type' => PaymentTypeEnum::ADVANCE->value,
            'status' => PaymentStatusEnum::COMPLETED->value,
            'stripe_payment_intent_id' => null,
            'paid_at' => now(),
        ];
    }

    public function stripe(string $paymentIntentId): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethodEnum::STRIPE->value,
            'stripe_payment_intent_id' => $paymentIntentId,
        ]);
    }
}
