<?php

use App\Enums\RoleEnum;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientPortal\ClientDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ==========================================
// RUTAS PÚBLICAS
// ==========================================
Route::post('/login', [UserController::class, 'login']);

// El Webhook de Stripe (Debe ser PÚBLICO para que Stripe pueda enviarte los eventos)
Route::post('/webhooks/stripe', [StripeController::class, 'handleWebhook']);

// ==========================================
// RUTAS PROTEGIDAS (Requieren Token)
// ==========================================
Route::middleware('auth:api')->group(function () {

    // Rutas Generales (Cualquier usuario logueado)
    Route::post('/logout', [UserController::class, 'logout']);
    Route::get('/me', [UserController::class, 'me']);
    // La app registra aqui el token de Firebase de su dispositivo.
    Route::post('/me/fcm-token', [UserController::class, 'updateFcmToken']);

    // ------------------------------------------
    // ZONA EXCLUSIVA PARA EL DUEÑO (ADMIN)
    // ------------------------------------------
    Route::middleware('role:'.RoleEnum::ADMIN->value)->prefix('admin')->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Generación de links de pago (Directamente en el grupo admin, sin anidamientos extra)
        Route::post('/stripe/create-session', [StripeController::class, 'createSession']);

        // Tu Core CRUD
        Route::apiResource('users', UserController::class);
        Route::apiResource('clients', ClientController::class);
        Route::apiResource('projects', ProjectController::class);
        Route::apiResource('services', ServiceController::class);
        Route::apiResource('payments', PaymentController::class);

    });

    // ------------------------------------------
    // ZONA EXCLUSIVA PARA LOS CLIENTES
    // ------------------------------------------
    Route::middleware('role:'.RoleEnum::CLIENT->value)->prefix('client')->group(function () {

        // Dashboard Principal del Portal del Cliente
        Route::get('/dashboard', [ClientDashboardController::class, 'index']);

    });

});
