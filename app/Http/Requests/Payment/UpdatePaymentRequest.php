<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rule;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La seguridad la maneja nuestro RoleMiddleware
    }

    public function rules(): array
    {
        // Extraemos el ID del pago desde la ruta (ej. PUT /api/admin/payments/5)
        $paymentId = $this->route('payment')->id ?? $this->route('payment');

        return [
            'client_id' => 'sometimes|required|integer|exists:clients,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'service_id' => 'nullable|integer|exists:services,id',
            'amount' => 'sometimes|required|numeric|min:0.01',
            'payment_method' => ['sometimes', 'required', new Enum(PaymentMethodEnum::class)],
            'payment_type' => ['sometimes', 'required', new Enum(PaymentTypeEnum::class)],
            'status' => ['sometimes', new Enum(PaymentStatusEnum::class)],
            
            // Regla especial: el ID de Stripe debe ser único, EXCEPTO para este mismo pago
            'stripe_payment_intent_id' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('payments', 'stripe_payment_intent_id')->ignore($paymentId),
            ],
            
            'paid_at' => 'nullable|date',
        ];
    }
}