<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // Ciclo de facturación para el cálculo real de MRR y ganancias
            // Opciones: 'monthly' (1 mes), 'quarterly' (3 meses), 'annually' (12 meses), 'biennially' (24 meses), 'one-time' (pago único)
            $table->string('billing_cycle')->default('monthly')->after('price_mxn'); 
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('billing_cycle');
        });
    }
};