<?php

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Enums\ServiceTypeEnum;
use App\Enums\ServiceStatusEnum;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'project_id' => 'sometimes|required|integer|exists:projects,id',
            'type' => ['sometimes', 'required', new Enum(ServiceTypeEnum::class)],
            'provider' => 'sometimes|required|string|max:100',
            'name' => 'sometimes|required|string|max:150',
            'cost_mxn' => 'sometimes|required|numeric|min:0',
            'price_mxn' => 'sometimes|required|numeric|min:0',
            'billing_cycle' => 'sometimes|in:monthly,quarterly,annually,biennially,one-time',
            'expiration_date' => 'sometimes|required|date',
            'status' => ['sometimes', 'required', new Enum(ServiceStatusEnum::class)],
        ];
    }
}