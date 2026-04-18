<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Pagination\LengthAwarePaginator;

class ClientService
{
    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        // Traemos los clientes ordenados por los más recientes
        return Client::latest()->paginate($perPage);
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