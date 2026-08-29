<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFcmTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La ruta ya exige auth:api y el token se guarda en el usuario
        // autenticado, nunca en uno indicado por el cliente.
        return true;
    }

    public function rules(): array
    {
        return [
            // max:255 no es arbitrario: es el ancho de la columna fcm_token.
            // Validar aqui devuelve un 422 claro en vez de truncar el token en
            // la base de datos y dejar de recibir notificaciones sin aviso.
            'fcm_token' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'fcm_token.required' => 'Falta el token del dispositivo.',
            'fcm_token.max' => 'El token del dispositivo excede los 255 caracteres admitidos.',
        ];
    }
}
