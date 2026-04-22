<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest; // <-- Nueva importación
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\UserService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    use ApiResponseTrait;

    protected $authService;
    protected $userService;

    public function __construct(AuthService $authService, UserService $userService)
    {
        $this->authService = $authService;
        $this->userService = $userService;
    }

    // --- MÉTODOS DE AUTENTICACIÓN ---

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
        return $this->successResponse($request->user(), 'Perfil recuperado.');
    }

    // --- MÉTODOS CRUD DE USUARIOS (Nuevos) ---

    public function index(): JsonResponse
    {
        $users = $this->userService->getAllUsers();

        return $this->successResponse(
            UserResource::collection($users),
            'Lista de usuarios obtenida.'
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->createUser($request->validated());

        return $this->successResponse(
            new UserResource($user),
            'Usuario creado exitosamente.',
            201 
        );
    }

    public function show(User $user): JsonResponse
    {
        return $this->successResponse(
            new UserResource($this->userService->getUser($user)),
            'Detalle del usuario obtenido.'
        );
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $updatedUser = $this->userService->updateUser($user, $request->validated());

        return $this->successResponse(
            new UserResource($updatedUser),
            'Usuario actualizado correctamente.'
        );
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        // Medida de seguridad extra: Evitar que el admin se borre a sí mismo usando el Request inyectado
        if ($request->user()->id === $user->id) {
             return $this->errorResponse('No puedes eliminar tu propia cuenta.', 403);
        }

        $this->userService->deleteUser($user);

        return $this->successResponse(null, 'Usuario eliminado correctamente.');
    }
}