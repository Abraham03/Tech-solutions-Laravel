<?php

namespace Tests\Feature;

use App\Enums\PaymentStatusEnum;
use App\Enums\PaymentTypeEnum;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_URL = '/api/webhooks/stripe';

    protected function setUp(): void
    {
        parent::setUp();
        // El push de Firebase no debe salir a la red durante los tests.
        Notification::fake();
    }

    /**
     * Firma el payload igual que Stripe: t=<ts>,v1=<hmac_sha256("ts.payload", secret)>
     */
    private function postSignedEvent(array $event, ?string $secret = null)
    {
        $payload = json_encode($event);
        $timestamp = time();
        $secret = $secret ?? config('services.stripe.webhook_secret');

        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return $this->call(
            'POST',
            self::WEBHOOK_URL,
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );
    }

    private function checkoutSessionEvent(array $metadata, array $overrides = []): array
    {
        return [
            'id' => 'evt_test_'.uniqid(),
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => array_merge([
                    'id' => 'cs_test_'.uniqid(),
                    'object' => 'checkout.session',
                    'amount_total' => 56000,
                    'currency' => 'mxn',
                    'payment_intent' => 'pi_test_'.uniqid(),
                    'payment_status' => 'paid',
                    'status' => 'complete',
                    'metadata' => $metadata,
                ], $overrides),
            ],
        ];
    }

    /**
     * LA REGRESION: renovacion de un servicio cuyo proyecto ya esta liquidado.
     * Antes devolvia 422 y el dinero quedaba cobrado en Stripe sin registro.
     */
    public function test_renewal_on_a_settled_project_is_recorded_and_extends_the_service(): void
    {
        $project = Project::factory()->completed()->create(['total_price' => 25000]);
        $service = Service::factory()->create([
            'project_id' => $project->id,
            'billing_cycle' => 'annually',
            'expiration_date' => now()->addDays(5)->toDateString(),
        ]);

        Payment::factory()->create([
            'client_id' => $project->client_id,
            'project_id' => $project->id,
            'amount' => 25000,
            'payment_type' => PaymentTypeEnum::FINAL->value,
        ]);

        $expectedExpiration = $service->expiration_date->copy()->addYear()->toDateString();

        $event = $this->checkoutSessionEvent([
            'client_id' => (string) $project->client_id,
            'project_id' => (string) $project->id,
            'service_id' => (string) $service->id,
            'payment_type' => 'renewal',
        ]);

        $response = $this->postSignedEvent($event);

        $response->assertOk();
        $this->assertDatabaseHas('payments', [
            'stripe_payment_intent_id' => $event['data']['object']['payment_intent'],
            'payment_type' => PaymentTypeEnum::RENEWAL->value,
            'status' => PaymentStatusEnum::COMPLETED->value,
            'amount' => 560.00,
        ]);

        $this->assertSame($expectedExpiration, $service->fresh()->expiration_date->toDateString());
    }

    /**
     * Un rechazo por regla de negocio no debe devolver 4xx: Stripe reintentaria
     * en bucle un evento que nunca va a pasar.
     */
    public function test_business_rule_rejection_answers_200_without_saving(): void
    {
        $project = Project::factory()->create(['total_price' => 1000]);

        $event = $this->checkoutSessionEvent([
            'client_id' => (string) $project->client_id,
            'project_id' => (string) $project->id,
            'payment_type' => 'advance',
        ], ['amount_total' => 500000]); // 5000 MXN: excede el total del proyecto

        $response = $this->postSignedEvent($event);

        $response->assertOk();
        $response->assertJson(['status' => 'rejected']);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_the_same_event_delivered_twice_creates_a_single_payment(): void
    {
        $project = Project::factory()->completed()->create(['total_price' => 25000]);
        $service = Service::factory()->create(['project_id' => $project->id]);

        $event = $this->checkoutSessionEvent([
            'client_id' => (string) $project->client_id,
            'project_id' => (string) $project->id,
            'service_id' => (string) $service->id,
            'payment_type' => 'renewal',
        ]);

        $this->postSignedEvent($event)->assertOk();
        $second = $this->postSignedEvent($event);

        $second->assertOk();
        $second->assertJson(['status' => 'already_processed']);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_an_invalid_signature_is_rejected(): void
    {
        $project = Project::factory()->create();

        $event = $this->checkoutSessionEvent([
            'client_id' => (string) $project->client_id,
            'payment_type' => 'renewal',
        ]);

        $response = $this->postSignedEvent($event, 'whsec_una_llave_que_no_es');

        $response->assertStatus(400);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_session_completed_without_payment_is_ignored(): void
    {
        $project = Project::factory()->create();

        $event = $this->checkoutSessionEvent([
            'client_id' => (string) $project->client_id,
            'payment_type' => 'renewal',
        ], ['payment_status' => 'unpaid']);

        $response = $this->postSignedEvent($event);

        $response->assertOk();
        $response->assertJson(['status' => 'ignored']);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_full_refund_marks_the_payment_as_refunded(): void
    {
        $payment = Payment::factory()->stripe('pi_test_reembolso')->create();

        $response = $this->postSignedEvent([
            'id' => 'evt_test_refund',
            'object' => 'event',
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'id' => 'ch_test_1',
                'object' => 'charge',
                'payment_intent' => 'pi_test_reembolso',
                'amount' => 100000,
                'amount_refunded' => 100000,
                'refunded' => true,
            ]],
        ]);

        $response->assertOk();
        $this->assertSame(PaymentStatusEnum::REFUNDED, $payment->fresh()->status);
    }

    /**
     * En un reembolso parcial el monto original sigue en la fila; marcarla como
     * reembolsada descuadraria los totales del dashboard.
     */
    public function test_a_partial_refund_leaves_the_status_untouched(): void
    {
        $payment = Payment::factory()->stripe('pi_test_parcial')->create();

        $response = $this->postSignedEvent([
            'id' => 'evt_test_partial',
            'object' => 'event',
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'id' => 'ch_test_2',
                'object' => 'charge',
                'payment_intent' => 'pi_test_parcial',
                'amount' => 100000,
                'amount_refunded' => 30000,
                'refunded' => false,
            ]],
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'partial_refund']);
        $this->assertSame(PaymentStatusEnum::COMPLETED, $payment->fresh()->status);
    }
}
