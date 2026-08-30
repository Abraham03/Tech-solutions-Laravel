<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateFcmTokenRequest;
use App\Http\Requests\User\UpdateUserRequest; // <-- Nueva importación
use App\Http\Resources\UserResource;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\AuthService;
use App\Services\UserService;
use App\Traits\ApiResponseTrait;
use App\Traits\HandlesListQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponseTrait, HandlesListQueries;

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

        if (! $result) {
            return $this->errorResponse('Credenciales incorrectas.', 401);
        }

        return $this->successResponse($result, 'Login exitoso.');
    }

    public function logout(Request $request): JsonResponse
    {
        // El token identifica al dispositivo que cierra sesion. Sin el no se
        // puede saber cual dar de baja, y darlos de baja todos desconectaria los
        // demas aparatos del usuario.
        $this->authService->logout($request->user(), $request->input('fcm_token'));

        return $this->successResponse(null, 'Sesión cerrada correctamente.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse($request->user(), 'Perfil recuperado.');
    }

    /**
     * Registra el token de Firebase del dispositivo que esta usando la sesion.
     *
     * La app (PWA de Angular o cliente nativo) debe llamarla al iniciar sesion y
     * cada vez que FCM rote el token, que ocurre sin aviso.
     */
    public function updateFcmToken(UpdateFcmTokenRequest $request): JsonResponse
    {
        $datos = $request->validated();

        // updateOrCreate por token y no por usuario: si alguien inicia sesion en
        // un dispositivo ya registrado con otra cuenta, la fila cambia de dueno
        // en vez de duplicarse. Y registrar el celular ya no borra el token del
        // escritorio, que era el problema de la columna unica.
        DeviceToken::updateOrCreate(
            ['token' => $datos['fcm_token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $datos['platform'] ?? null,
                'last_used_at' => now(),
            ]
        );

        return $this->successResponse(null, 'Token de notificaciones registrado.');
    }

    // --- MÉTODOS CRUD DE USUARIOS (Nuevos) ---

    public function index(Request $request): JsonResponse
    {
        $users = $this->userService->getAllPaginated(
            $this->perPage($request),
            $this->searchTerm($request)
        );

        // ->response()->getData(true) para devolver data + links + meta, igual
        // que el resto de listados. Antes este era el unico que respondia con un
        // array plano, asi que el frontend tenia que tratarlo aparte.
        return $this->successResponse(
            UserResource::collection($users)->response()->getData(true),
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
