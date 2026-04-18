<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\StripeController;
use App\Enums\RoleEnum;

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

    // ------------------------------------------
    // ZONA EXCLUSIVA PARA EL DUEÑO (ADMIN)
    // ------------------------------------------
    Route::middleware('role:' . RoleEnum::ADMIN->value)->prefix('admin')->group(function () {
        
        Route::get('/dashboard-stats', function () {
            return response()->json(['message' => 'Bienvenido a la zona de Dueño, Jefe.']);
        });

        // Generación de links de pago (Directamente en el grupo admin, sin anidamientos extra)
        Route::post('/stripe/create-session', [StripeController::class, 'createSession']);

        // Tu Core CRUD
        Route::apiResource('clients', \App\Http\Controllers\ClientController::class);
        Route::apiResource('projects', \App\Http\Controllers\ProjectController::class);
        Route::apiResource('services', \App\Http\Controllers\ServiceController::class);
        Route::apiResource('payments', \App\Http\Controllers\PaymentController::class);
        
    });

    // ------------------------------------------
    // ZONA EXCLUSIVA PARA LOS CLIENTES
    // ------------------------------------------
    Route::middleware('role:' . RoleEnum::CLIENT->value)->prefix('client')->group(function () {
        
        Route::get('/my-projects', function () {
            return response()->json(['message' => 'Aquí están tus proyectos, estimado cliente.']);
        });

    });

});