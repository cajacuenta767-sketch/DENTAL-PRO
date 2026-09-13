<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_clinicos', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 30)->unique();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctores')->restrictOnDelete();
            $table->foreignId('cita_id')->nullable()->constrained('citas')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->enum('tipo', [
                'RECETA', 'CERTIFICADO', 'ORDEN_LABORATORIO', 'CONSENTIMIENTO', 'REFERENCIA', 'CONSTANCIA',
            ])->default('RECETA');
            $table->string('titulo', 180);
            $table->text('contenido');
            $table->text('indicaciones')->nullable();
            $table->unsignedSmallInteger('vigencia_dias')->nullable();
            $table->date('fecha_emision');
            $table->enum('estado', ['EMITIDO', 'ANULADO'])->default('EMITIDO');
            $table->timestamps();

            $table->index(['paciente_id', 'tipo']);
            $table->index(['fecha_emision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_clinicos');
    }
};
