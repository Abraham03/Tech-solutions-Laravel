<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // Solo para servicios tipo 'basketpro_subscription': a qué cuenta de BasketPro
            // corresponde y con qué plan. El código es único porque una cuenta de BasketPro
            // la cobra un solo servicio.
            $table->string('basketpro_tenant_code', 30)->nullable()->unique()->after('status');
            $table->string('basketpro_plan_code', 30)->nullable()->after('basketpro_tenant_code');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropUnique(['basketpro_tenant_code']);
            $table->dropColumn(['basketpro_tenant_code', 'basketpro_plan_code']);
        });
    }
};
