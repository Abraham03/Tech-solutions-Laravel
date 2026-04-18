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
            $table->enum('type', ['web', 'flutter_app', 'backend', 'other']);
            $table->decimal('total_price', 10, 2);
            $table->string('currency', 3)->default('MXN');
            $table->enum('status', ['quoted', 'development', 'completed', 'suspended']);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};