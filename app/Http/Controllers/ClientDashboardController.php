<?php

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Services\ClientPortalService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class ClientDashboardController extends Controller
{
    use ApiResponseTrait;

    protected $clientPortalService;

    public function __construct(ClientPortalService $clientPortalService)
    {
        $this->clientPortalService = $clientPortalService;
    }

    /**
     * Retorna el resumen del dashboard para el cliente autenticado.
     */
    public function index(): JsonResponse
    {
        $summary = $this->clientPortalService->getDashboardSummary();
        return $this->successResponse($summary, 'Dashboard del cliente cargado con éxito.');
    }
}