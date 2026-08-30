<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Traits\ApiResponseTrait;
use App\Traits\HandlesListQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponseTrait, HandlesListQueries;

    private DashboardService $dashboardService;

    // Inyección de dependencias (Principio SOLID)
    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Métricas y recortes cortos para el primer pintado del panel.
     *
     * Sigue devolviendo las listas recortadas de siempre para que la pantalla
     * tenga algo que mostrar de inmediato; cada pestana pide luego su pagina a
     * los endpoints de abajo.
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ==========================================
    // LISTADOS PAGINADOS, UNO POR PESTANA
    //
    // Devuelven la forma estandar de Laravel (data + links + meta), la misma que
    // el resto de listados del panel, para que el frontend los consuma igual.
    // ==========================================

    /** Pestana Resumen: proyectos recientes. */
    public function recentProjects(Request $request): JsonResponse
    {
        return response()->json($this->paginatedResponse(
            $this->dashboardService->paginatedRecentProjects($this->perPage($request))
        ));
    }

    /** Pestana Resumen: valor acumulado por cliente. */
    public function clientLtv(Request $request): JsonResponse
    {
        return response()->json($this->paginatedResponse(
            $this->dashboardService->paginatedClientLtv($this->perPage($request))
        ));
    }

    /**
     * Pestana Servicios: los que vencen pronto.
     *
     * La ventana en dias es configurable porque 30 sirve para vigilar, pero al
     * revisar a fondo interesa mirar mas lejos.
     */
    public function expiringServices(Request $request): JsonResponse
    {
        $dias = (int) $request->query('days', 30);
        // Acotado para que nadie pida un rango absurdo desde la URL.
        $dias = max(1, min($dias, 365));

        return response()->json($this->paginatedResponse(
            $this->dashboardService->paginatedExpiringServices($this->perPage($request), $dias)
        ));
    }

    /** Pestanas Ingresos y Servicios: margen por servicio activo. */
    public function serviceMargins(Request $request): JsonResponse
    {
        return response()->json($this->paginatedResponse(
            $this->dashboardService->paginatedServiceMargins($this->perPage($request))
        ));
    }

    /**
     * Pestana Notificaciones.
     *
     * El filtro por canal se aplica en la base: hacerlo en el navegador solo
     * filtraria la pagina cargada, que es el fallo que ya teniamos en las tablas.
     */
    public function notifications(Request $request): JsonResponse
    {
        $canal = $request->query('type');
        $canalesValidos = ['whatsapp_reminder', 'email_invoice', 'push_alert'];

        if ($canal !== null && ! in_array($canal, $canalesValidos, true)) {
            $canal = null;
        }

        return response()->json($this->paginatedResponse(
            $this->dashboardService->paginatedNotifications($this->perPage($request), $canal)
        ));
    }
}
