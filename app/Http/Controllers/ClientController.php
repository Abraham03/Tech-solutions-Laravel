<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Services\ClientService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class ClientController extends Controller
{
    use ApiResponseTrait;

    protected $clientService;

    // Inyección del Servicio por constructor
    public function __construct(ClientService $clientService)
    {
        $this->clientService = $clientService;
    }

    public function index(): JsonResponse
    {
        $clients = $this->clientService->getAllPaginated();
        
        // Devolvemos la colección transformada por nuestro Resource
        return $this->successResponse(
            ClientResource::collection($clients)->response()->getData(true),
            'Lista de clientes obtenida.'
        );
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = $this->clientService->createClient($request->validated());

        return $this->successResponse(
            new ClientResource($client),
            'Cliente registrado exitosamente.',
            201 // 201 Created
        );
    }

    public function show(Client $client): JsonResponse
    {
        return $this->successResponse(
            new ClientResource($client),
            'Detalle de cliente.'
        );
    }

    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        $updatedClient = $this->clientService->updateClient($client, $request->validated());

        return $this->successResponse(
            new ClientResource($updatedClient),
            'Cliente actualizado correctamente.'
        );
    }

    public function destroy(Client $client): JsonResponse
    {
        $this->clientService->deleteClient($client);

        return $this->successResponse(null, 'Cliente eliminado correctamente.');
    }
}