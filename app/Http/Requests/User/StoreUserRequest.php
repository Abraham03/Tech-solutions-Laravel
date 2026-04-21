<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use App\Enums\RoleEnum;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización ya la maneja tu middleware de Rol en api.php
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'email' => 'required|string|email|max:255|unique:users,email',
            // Contraseña fuerte: min 8, letras mixtas y números
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
            'role' => ['required', new Enum(RoleEnum::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Este correo electrónico ya está registrado en el sistema.',
            'password.mixed_case' => 'La contraseña debe contener al menos una letra mayúscula y una minúscula.',
            'password.numbers' => 'La contraseña debe contener al menos un número.',
        ];
    }
}