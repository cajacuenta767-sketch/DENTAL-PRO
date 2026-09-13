<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presupuestos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('doctores')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('odontograma_id')->nullable()->constrained('odontogramas')->nullOnDelete();
            $table->enum('estado', [
                'BORRADOR', 'PRESENTADO', 'APROBADO', 'EN_EJECUCION', 'COMPLETADO', 'RECHAZADO',
            ])->default('BORRADOR');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('cobertura_seguro', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->unsignedSmallInteger('validez_dias')->default(30);
            $table->date('fecha');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['paciente_id', 'estado']);
            $table->index(['fecha']);
        });

        Schema::create('presupuesto_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presupuesto_id')->constrained('presupuestos')->cascadeOnDelete();
            $table->foreignId('tratamiento_id')->nullable()->constrained('tratamientos')->nullOnDelete();
            $table->foreignId('cita_id')->nullable()->constrained('citas')->nullOnDelete();
            $table->string('pieza_dental', 10)->nullable()->comment('Numeración FDI');
            $table->string('cara', 20)->nullable();
            $table->string('descripcion', 255);
            $table->unsignedInteger('cantidad')->default(1);
            $table->decimal('precio_unitario', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->enum('estado', ['PENDIENTE', 'EN_PROCESO', 'EJECUTADO', 'ANULADO'])->default('PENDIENTE');
            $table->date('fecha_ejecucion')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['presupuesto_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuesto_detalles');
        Schema::dropIfExists('presupuestos');
    }
};
