<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Pagination\LengthAwarePaginator;

class ClientService
{
    public function getAllPaginated(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        // Traemos los clientes ordenados por los más recientes
        return Client::query()
            // La busqueda va agrupada en su propio where: sin el parentesis, el
            // primer orWhere anularia cualquier filtro anterior de la consulta.
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate($perPage)
            // Conserva search y per_page en los enlaces de paginacion; sin esto,
            // pasar a la pagina 2 perderia la busqueda.
            ->withQueryString();
    }

    public function createClient(array $data): Client
    {
        // Aquí podríamos agregar la lógica futura para registrar al cliente en Stripe automáticamente
        return Client::create($data);
    }

    public function updateClient(Client $client, array $data): Client
    {
        $client->update($data);

        return $client;
    }

    public function deleteClient(Client $client): void
    {
        // Como tenemos SoftDeletes, esto no lo borra físicamente, solo llena el deleted_at
        $client->delete();
    }
}
