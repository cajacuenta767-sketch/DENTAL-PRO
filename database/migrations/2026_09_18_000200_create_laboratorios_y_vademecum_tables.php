<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Laboratorios dentales externos / protésicos
        Schema::create('laboratorios_dentales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->nullable()->constrained('clinicas')->nullOnDelete();
            $table->string('nombre', 150);
            $table->string('contacto', 120)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('direccion')->nullable();
            $table->string('especialidades', 255)->nullable()->comment('Prótesis fija, removible, ortodoncia, etc.');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Órdenes de trabajo para laboratorio dental
        Schema::create('ordenes_laboratorio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->nullable()->constrained('clinicas')->nullOnDelete();
            $table->string('folio', 20)->unique();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctores')->restrictOnDelete();
            $table->foreignId('laboratorio_id')->constrained('laboratorios_dentales')->restrictOnDelete();
            $table->foreignId('cita_id')->nullable()->constrained('citas')->nullOnDelete();
            $table->foreignId('tratamiento_id')->nullable()->constrained('tratamientos')->nullOnDelete();
            $table->string('tipo_trabajo', 150);
            $table->string('color_vita', 20)->nullable();
            $table->string('piezas_dentales', 100)->nullable();
            $table->date('fecha_envio');
            $table->date('fecha_prometida');
            $table->date('fecha_entrega')->nullable();
            $table->decimal('costo_laboratorio', 10, 2)->default(0);
            $table->decimal('precio_paciente', 10, 2)->default(0);
            $table->string('estado', 30)->default('ENVIADO'); // ENVIADO, EN_PROCESO, PRUEBA, TERMINADO, ENTREGADO, AJUSTE
            $table->text('notas_tecnicas')->nullable();
            $table->string('adjunto_archivo', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['paciente_id', 'estado']);
            $table->index(['laboratorio_id', 'fecha_prometida']);
            $table->index('estado');
        });

        // 3. Vademécum odontológico precargado
        Schema::create('medicamentos_vademecum', function (Blueprint $table) {
            $table->id();
            $table->string('principio_activo', 150);
            $table->string('nombre_comercial', 150)->nullable();
            $table->string('presentacion', 100)->comment('Comprimidos, Cápsulas, Suspensión, Gel, etc.');
            $table->string('concentracion', 60);
            $table->string('familia', 50)->comment('PENICILINA, MACROLIDO, AINE, OPIOIDE, etc.');
            $table->text('posologia_adulto')->nullable();
            $table->text('posologia_pediatrica')->nullable();
            $table->text('contraindicaciones')->nullable();
            $table->text('advertencias')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('familia');
            $table->index('principio_activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_laboratorio');
        Schema::dropIfExists('laboratorios_dentales');
        Schema::dropIfExists('medicamentos_vademecum');
    }
};
