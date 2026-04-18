<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Traits\ApiResponseTrait;

class CheckRole
{
    use ApiResponseTrait;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // 1. Verificamos que el usuario esté autenticado
        if (! $request->user()) {
            return $this->errorResponse('No autenticado.', 401);
        }

        // 2. Verificamos que el rol coincida con el requerido en la ruta
        // Como aplicamos el Enum en el modelo, usamos ->value para compararlo con el string que llega de la ruta
        if ($request->user()->role->value !== $role) {
            return $this->errorResponse('Acceso denegado. Permisos insuficientes.', 403);
        }

        // 3. Si todo está bien, dejamos pasar la petición al Controlador
        return $next($request);
    }
}