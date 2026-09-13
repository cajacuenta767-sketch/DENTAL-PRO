<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citas', function (Blueprint $table) {
            $table->id();
            $table->string('token', 12)->unique();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctores')->restrictOnDelete();
            $table->foreignId('tratamiento_id')->constrained('tratamientos')->restrictOnDelete();
            $table->date('fecha');
            $table->time('hora');
            $table->enum('estado', ['PENDIENTE', 'CONFIRMADA', 'EN_CURSO', 'COMPLETADA', 'CANCELADA'])->default('PENDIENTE');
            $table->enum('origen', ['RECEPCION', 'ONLINE'])->default('RECEPCION');
            $table->text('motivo')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamp('recordatorio_enviado_en')->nullable();
            $table->timestamps();

            $table->index(['fecha', 'estado']);
            $table->index(['doctor_id', 'fecha']);
            $table->unique(['doctor_id', 'fecha', 'hora'], 'citas_doctor_slot_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citas');
    }
};
