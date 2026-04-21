<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Services\DashboardService;
use App\Traits\ApiResponseTrait; // Veo que tienes este trait en tu carpeta Traits

class DashboardController extends Controller
{
    use ApiResponseTrait;

    private DashboardService $dashboardService;

    // Inyección de dependencias (Principio SOLID)
    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Retorna las métricas y datos recientes para el panel principal del Admin.
     */
    public function index(): JsonResponse
    {
        try {
            $data = $this->dashboardService->getAdminSummary();
            
            // Retornamos el JSON crudo que espera Angular directamente
            return response()->json($data, 200);

        } catch (\Exception $e) {
            // Manejo de errores profesional
            return response()->json([
                'message' => 'Error al cargar el dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}