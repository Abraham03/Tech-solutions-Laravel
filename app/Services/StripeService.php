<?php

namespace App\Services;

use Stripe\StripeClient;
use Stripe\Checkout\Session;

class StripeService
{
    protected $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Crea una sesión de Checkout para que el cliente pague.
     */
    public function createCheckoutSession(array $data): Session
    {
        // Stripe cobra en centavos y exige un entero. Redondeamos explicitamente porque
        // multiplicar un decimal por 100 en punto flotante puede truncar hacia abajo
        // (560.10 * 100 da 56009.999... y PHP lo castea a 56009).
        $unitAmount = (int) round(((float) $data['amount']) * 100);

        if ($unitAmount <= 0) {
            throw new \InvalidArgumentException('El monto del cobro debe ser mayor a cero.');
        }

        return $this->stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'mxn',
                    'product_data' => [
                        'name' => $data['description'],
                    ],
                    'unit_amount' => $unitAmount,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => config('app.url') . '/payment-success',
            'cancel_url' => config('app.url') . '/payment-cancel',
            // Pasamos metadatos para que el Webhook sepa qué estamos pagando
            'metadata' => [
                'client_id' => $data['client_id'],
                'project_id' => $data['project_id'] ?? null,
                'service_id' => $data['service_id'] ?? null,
                'payment_type' => $data['payment_type'],
            ],
        ]);
    }
}