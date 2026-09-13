<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estudios_imagen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('doctores')->nullOnDelete();
            $table->foreignId('cita_id')->nullable()->constrained('citas')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->enum('tipo', [
                'PANORAMICA', 'PERIAPICAL', 'BITEWING', 'OCLUSAL',
                'CBCT', 'CEFALOMETRICA', 'FOTO_INTRAORAL', 'FOTO_EXTRAORAL', 'OTRO',
            ])->default('PANORAMICA');
            $table->string('titulo', 150);
            $table->string('archivo', 255);
            $table->string('nombre_original', 255)->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('tamano')->nullable()->comment('Bytes');
            $table->string('piezas_referidas', 255)->nullable();
            $table->date('fecha_estudio');
            $table->text('hallazgos')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['paciente_id', 'fecha_estudio']);
            $table->index(['tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudios_imagen');
    }
};
