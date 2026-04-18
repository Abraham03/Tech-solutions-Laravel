<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Enums\ProjectTypeEnum;
use App\Enums\ProjectStatusEnum;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'client_id' => 'sometimes|required|integer|exists:clients,id',
            'name' => 'sometimes|required|string|max:150',
            'type' => ['sometimes', 'required', new Enum(ProjectTypeEnum::class)],
            'total_price' => 'sometimes|required|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'status' => ['sometimes', 'required', new Enum(ProjectStatusEnum::class)],
        ];
    }
}