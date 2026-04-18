<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Services\PaymentService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    use ApiResponseTrait;

    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function index(): JsonResponse
    {
        $payments = $this->paymentService->getAllPaginated();
        
        return $this->successResponse(
            PaymentResource::collection($payments)->response()->getData(true),
            'Historial de pagos obtenido.'
        );
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->createPayment($request->validated());

        return $this->successResponse(
            new PaymentResource($payment->load(['client', 'project', 'service'])),
            'Pago registrado correctamente.',
            201
        );
    }

    public function show(Payment $payment): JsonResponse
    {
        return $this->successResponse(
            new PaymentResource($payment->load(['client', 'project', 'service'])),
            'Detalle del pago.'
        );
    }

    public function update(UpdatePaymentRequest $request, Payment $payment): JsonResponse
    {
        $updatedPayment = $this->paymentService->updatePayment($payment, $request->validated());

        return $this->successResponse(
            new PaymentResource($updatedPayment->load(['client', 'project', 'service'])),
            'Pago actualizado correctamente.'
        );
    }

    public function destroy(Payment $payment): JsonResponse
    {
        $this->paymentService->deletePayment($payment);

        return $this->successResponse(null, 'Pago eliminado correctamente.');
    }
}