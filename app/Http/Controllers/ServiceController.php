<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Services\InfrastructureService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class ServiceController extends Controller
{
    use ApiResponseTrait;

    protected $infrastructureService;

    public function __construct(InfrastructureService $infrastructureService)
    {
        $this->infrastructureService = $infrastructureService;
    }

    public function index(): JsonResponse
    {
        $services = $this->infrastructureService->getAllPaginated();
        
        return $this->successResponse(
            ServiceResource::collection($services)->response()->getData(true),
            'Lista de servicios obtenida.'
        );
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = $this->infrastructureService->createService($request->validated());

        return $this->successResponse(
            new ServiceResource($service->load('project')),
            'Servicio registrado exitosamente.',
            201
        );
    }

    public function show(Service $service): JsonResponse
    {
        return $this->successResponse(
            new ServiceResource($service->load('project')),
            'Detalle del servicio.'
        );
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $updatedService = $this->infrastructureService->updateService($service, $request->validated());

        return $this->successResponse(
            new ServiceResource($updatedService->load('project')),
            'Servicio actualizado correctamente.'
        );
    }

    public function destroy(Service $service): JsonResponse
    {
        $this->infrastructureService->deleteService($service);

        return $this->successResponse(null, 'Servicio eliminado correctamente.');
    }
}