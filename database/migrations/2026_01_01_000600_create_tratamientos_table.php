<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tratamientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('especialidad_id')->constrained('especialidades')->restrictOnDelete();
            $table->string('nombre', 150);
            $table->string('descripcion', 255)->nullable();
            $table->decimal('precio', 10, 2)->default(0);
            $table->unsignedInteger('duracion')->default(30)->comment('Duración en minutos');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['especialidad_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tratamientos');
    }
};
