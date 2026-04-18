<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponseTrait;

    protected $authService;

    // Inyección de dependencias (SOLID)
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        if (!$result) {
            return $this->errorResponse('Credenciales incorrectas.', 401);
        }

        return $this->successResponse($result, 'Login exitoso.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->successResponse(null, 'Sesión cerrada correctamente.');
    }

    public function me(Request $request): JsonResponse
    {
        // Devuelve los datos del usuario actualmente autenticado (Útil para que Angular reconstruya la sesión al recargar la página)
        return $this->successResponse($request->user(), 'Perfil recuperado.');
    }
}