<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Estado de la licencia de esta instalación (una sola fila).
        Schema::create('licencias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_instalacion', 9)->unique();
            $table->string('ancla', 20)->nullable();
            $table->string('tipo', 10)->nullable();
            $table->unsignedSmallInteger('dia_fin')->nullable();
            $table->date('vence_en')->nullable();
            $table->timestamp('activada_en')->nullable();
            $table->timestamp('renovada_en')->nullable();
            $table->json('historial')->nullable();
            $table->timestamps();
        });

        // Registro del proveedor: clientes a los que emitió licencias.
        Schema::create('licencias_emitidas', function (Blueprint $table) {
            $table->id();
            $table->string('cliente', 120);
            $table->string('contacto', 120)->nullable();
            $table->text('semilla');
            $table->string('ancla', 20);
            $table->string('tipo', 10);
            $table->unsignedSmallInteger('dia_fin');
            $table->string('codigo_instalacion', 9)->nullable();
            $table->text('ultimo_codigo')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licencias_emitidas');
        Schema::dropIfExists('licencias');
    }
};
