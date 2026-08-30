<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Unico a nivel global y no por usuario: un mismo navegador no puede
            // pertenecer a dos cuentas a la vez. Si alguien mas inicia sesion en
            // ese dispositivo, la fila cambia de dueno en vez de duplicarse.
            $table->string('token', 255)->unique();
            $table->string('platform', 40)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        // Traspaso de lo que ya existe: sin esto, los dispositivos registrados
        // dejarian de recibir notificaciones al desplegar.
        DB::table('users')
            ->whereNotNull('fcm_token')
            ->orderBy('id')
            ->select('id', 'fcm_token', 'updated_at')
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    DB::table('device_tokens')->insertOrIgnore([
                        'user_id' => $user->id,
                        'token' => $user->fcm_token,
                        'platform' => null,
                        'last_used_at' => $user->updated_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        // La columna users.fcm_token se conserva a proposito. Eliminarla aqui
        // haria que, entre el despliegue del codigo y la ejecucion manual de la
        // migracion, cualquier version anterior reventara. Se retira en una
        // migracion posterior, cuando esto lleve semanas en produccion.
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
