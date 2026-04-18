<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'client_id' => 'required|integer|exists:clients,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'service_id' => 'nullable|integer|exists:services,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => ['required', new Enum(PaymentMethodEnum::class)],
            'payment_type' => ['required', new Enum(PaymentTypeEnum::class)],
            'status' => ['sometimes', new Enum(PaymentStatusEnum::class)],
            'stripe_payment_intent_id' => 'nullable|string|max:100|unique:payments',
            'paid_at' => 'nullable|date',
        ];
    }
}