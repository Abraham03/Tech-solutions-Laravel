<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Collection;

class UserService
{
    /**
     * Obtiene todos los usuarios ordenados por los más recientes.
     */
    public function getAllUsers(): Collection
    {
        return User::orderBy('created_at', 'desc')->get();
    }

    /**
     * Obtiene un usuario específico.
     */
    public function getUser(User $user): User
    {
        return $user;
    }

    /**
     * Crea un nuevo usuario y encripta su contraseña.
     */
    public function createUser(array $data): User
    {
        $data['password'] = Hash::make($data['password']);

        return User::create($data);
    }

    /**
     * Actualiza un usuario existente. Si la contraseña viene vacía, no se actualiza.
     */
    public function updateUser(User $user, array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            // Si no se envía contraseña, eliminamos la llave para que no sobreescriba con null
            unset($data['password']); 
        }

        $user->update($data);

        return $user;
    }

    /**
     * Elimina un usuario. Si usas SoftDeletes en tu modelo User, esto solo lo ocultará.
     */
    public function deleteUser(User $user): void
    {
        $user->delete();
    }
}