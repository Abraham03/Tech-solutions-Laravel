<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Obtenemos el ID del cliente desde la URL de la petición (ej. PUT /api/admin/clients/5)
        $clientId = $this->route('client')->id ?? $this->route('client');

        return [
            'name' => 'sometimes|required|string|max:100',
            'contact_name' => 'sometimes|required|string|max:100',
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:150',
                Rule::unique('clients', 'email')->ignore($clientId),
            ],
            'phone_number' => 'sometimes|required|string|max:20',
        ];
    }
}