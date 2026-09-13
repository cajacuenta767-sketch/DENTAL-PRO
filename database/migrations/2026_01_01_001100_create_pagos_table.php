<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_recibo', 30)->unique();
            $table->foreignId('paciente_id')->constrained('pacientes')->restrictOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('doctores')->nullOnDelete();
            $table->foreignId('cita_id')->nullable()->constrained('citas')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete()->comment('Cajero responsable');
            $table->decimal('monto_total', 10, 2)->default(0);
            $table->decimal('monto_pagado', 10, 2)->default(0);
            $table->decimal('monto_saldo', 10, 2)->default(0);
            $table->enum('metodo_pago', ['EFECTIVO', 'TARJETA', 'QR', 'TRANSFERENCIA'])->default('EFECTIVO');
            $table->enum('estado', ['PENDIENTE', 'PARCIAL', 'COMPLETADO', 'ANULADO'])->default('PENDIENTE');
            $table->text('notas')->nullable();
            $table->timestamp('fecha_pago');
            $table->timestamps();

            $table->index(['fecha_pago', 'estado']);
            $table->index(['metodo_pago']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
