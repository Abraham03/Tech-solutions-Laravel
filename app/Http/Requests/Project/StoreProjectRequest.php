<?php

namespace App\Http\Requests\Project;

use App\Enums\ProjectStatusEnum;
use App\Enums\ProjectTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => 'required|integer|exists:clients,id',
            'name' => 'required|string|max:150',
            'type' => ['required', new Enum(ProjectTypeEnum::class)],
            'total_price' => 'required|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'status' => ['required', new Enum(ProjectStatusEnum::class)],
        ];
    }
}
