<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Obtiene los usuarios paginados, con busqueda opcional.
     *
     * Antes devolvia la tabla entera con get(): con unas decenas de usuarios da
     * igual, pero es el unico modulo que no paginaba y no hay motivo para que
     * se comporte distinto de los demas.
     */
    public function getAllPaginated(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return User::query()
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('role', 'like', "%{$search}%");
            }))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();
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
