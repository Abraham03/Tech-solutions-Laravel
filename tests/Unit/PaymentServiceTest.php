<?php

namespace Tests\Unit;

use App\Enums\PaymentStatusEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\ProjectStatusEnum;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Service;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentService = new PaymentService();
    }

    /**
     * La regresion que rompio los cobros: un servicio de un proyecto ya liquidado
     * no puede rechazarse por "saldo excedido", porque su renovacion es un cobro aparte.
     */
    public function test_renewal_is_saved_even_when_the_project_is_fully_paid(): void
    {
        $project = Project::factory()->completed()->create(['total_price' => 25000]);
        $service = Service::factory()->create(['project_id' => $project->id]);

        // El proyecto ya esta liquidado por completo.
        Payment::factory()->create([
            'client_id' => $project->client_id,
            'project_id' => $project->id,
            'amount' => 25000,
            'payment_type' => PaymentTypeEnum::FINAL->value,
        ]);

        $payment = $this->paymentService->createPayment([
            'client_id' => $project->client_id,
            'project_id' => $project->id,
            'service_id' => $service->id,
            'amount' => 560,
            'payment_method' => 'stripe',
            'payment_type' => PaymentTypeEnum::RENEWAL->value,
            'status' => PaymentStatusEnum::COMPLETED->value,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_type' => PaymentTypeEnum::RENEWAL->value,
            'amount' => 560.00,
        ]);
    }

    public function test_advance_that_exceeds_the_project_balance_is_rejected(): void
    {
        $project = Project::factory()->create(['total_price' => 10000]);

        Payment::factory()->create([
            'client_id' => $project->client_id,
            'project_id' => $project->id,
            'amount' => 9000,
        ]);

        $this->expectException(ValidationException::class);

        $this->paymentService->createPayment([
            'client_id' => $project->client_id,
            'project_id' => $project->id,
            'amount' => 5000, // 9000 + 5000 > 10000
            'payment_method' => 'transfer',
            'payment_type' => PaymentTypeEnum::ADVANCE->value,
            'status' => PaymentStatusEnum::COMPLETED->value,
        ]);
    }

    public function test_payment_that_settles_the_balance_closes_the_project(): void
    {
        $project = Project::factory()->create(['total_price' => 10000]);

        Payment::factory()->create([
            'client_id' => $project->client_id,
            'project_id' => $project->id,
            'amount' => 6000,
        ]);

        $this->paymentService->createPayment([
            'client_id' => $project->client_id,
            'project_id' => $project->id,
            'amount' => 4000, // liquida exactamente
            'payment_method' => 'transfer',
            'payment_type' => PaymentTypeEnum::FINAL->value,
            'status' => PaymentStatusEnum::COMPLETED->value,
        ]);

        $this->assertSame(
            ProjectStatusEnum::COMPLETED,
            $project->fresh()->status
        );
    }

    /**
     * Si las renovaciones contaran como abono, el saldo del proyecto se veria
     * artificialmente pagado y el dashboard mentiria.
     */
    public function test_renewals_do_not_count_toward_the_project_balance(): void
    {
        $project = Project::factory()->create(['total_price' => 10000]);
        $service = Service::factory()->create(['project_id' => $project->id]);

        Payment::factory()->create([
            'client_id' => $project->client_id,
            'project_id' => $project->id,
            'amount' => 4000,
            'payment_type' => PaymentTypeEnum::ADVANCE->value,
        ]);

        Payment::factory()->create([
            'client_id' => $project->client_id,
            'project_id' => $project->id,
            'service_id' => $service->id,
            'amount' => 560,
            'payment_type' => PaymentTypeEnum::RENEWAL->value,
        ]);

        // Solo el anticipo cuenta: 4000, no 4560.
        $this->assertEquals(4000, $project->fresh()->paid_amount);
        $this->assertEquals(6000, $project->fresh()->balance);
    }
}
