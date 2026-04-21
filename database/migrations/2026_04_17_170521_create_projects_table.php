<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name', 150);
            
            // ESCALABILIDAD: Cambiamos enum por string. La validación la hace tu código PHP.
            $table->string('type', 50); 
            
            $table->decimal('total_price', 10, 2);
            $table->string('currency', 3)->default('MXN');
            
            // ESCALABILIDAD: Cambiamos enum por string.
            $table->string('status', 50)->default('quoted'); 
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};