<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * Intenta autenticar a un usuario y genera un token JWT.
     */
    public function login(array $credentials): ?array
    {
        $user = User::where('email', $credentials['email'])->first();

        // Verificamos si el usuario existe y si la contraseña coincide
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return null;
        }

        // Generamos el token de Passport
        $tokenResult = $user->createToken('TechSolutionsAuthToken');

        return [
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role, // Vital para saber si es Admin o Cliente
            ],
            'access_token' => $tokenResult->accessToken,
            'token_type'   => 'Bearer',
        ];
    }

    /**
     * Revoca el token actual del usuario (Logout).
     */
    public function logout(User $user): void
    {
        // En Passport, el token actual se puede acceder y revocar así
        $user->token()->revoke();
    }
}