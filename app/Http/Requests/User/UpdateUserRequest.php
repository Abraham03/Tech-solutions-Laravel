<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use App\Enums\RoleEnum;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:150',
            // El correo debe ser único, ignorando el ID del usuario actual
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $this->user->id,
            // La contraseña es opcional al actualizar
            'password' => ['nullable', 'string', Password::min(8)->mixedCase()->numbers()],
            'role' => ['sometimes', new Enum(RoleEnum::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Este correo electrónico ya está en uso por otro usuario.',
            'password.mixed_case' => 'La contraseña debe contener al menos una letra mayúscula y una minúscula.',
            'password.numbers' => 'La contraseña debe contener al menos un número.',
        ];
    }
}