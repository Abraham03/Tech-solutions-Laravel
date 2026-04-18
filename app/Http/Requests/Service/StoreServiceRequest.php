<?php

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Enums\ServiceTypeEnum;
use App\Enums\ServiceStatusEnum;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'project_id' => 'required|integer|exists:projects,id',
            'type' => ['required', new Enum(ServiceTypeEnum::class)],
            'provider' => 'required|string|max:100',
            'name' => 'required|string|max:150',
            'cost_mxn' => 'required|numeric|min:0',
            'price_mxn' => 'required|numeric|min:0',
            'expiration_date' => 'sometimes|date',
            'status' => ['sometimes', new Enum(ServiceStatusEnum::class)],
        ];
    }
}