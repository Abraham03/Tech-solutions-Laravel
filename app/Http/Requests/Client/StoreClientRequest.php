<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización ya la maneja nuestro Middleware de Roles
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'contact_name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:clients,email',
            'phone_number' => 'required|string|max:20',
        ];
    }
}