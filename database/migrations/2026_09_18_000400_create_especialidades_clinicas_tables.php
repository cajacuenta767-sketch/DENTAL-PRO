<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Table conductometrias (Endodoncia)
        if (! Schema::hasTable('conductometrias')) {
            Schema::create('conductometrias', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinica_id')->nullable()->constrained('clinicas')->cascadeOnDelete();
                $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
                $table->foreignId('doctor_id')->nullable()->constrained('doctores')->nullOnDelete();
                $table->foreignId('cita_id')->nullable()->constrained('citas')->nullOnDelete();
                $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->integer('diente');
                $table->string('diagnostico_pulpar', 100)->nullable();
                $table->string('diagnostico_periapical', 100)->nullable();
                $table->json('conductos');
                $table->string('solucion_irrigante', 150)->nullable();
                $table->string('medicacion_intraconducto', 150)->nullable();
                $table->string('cemento_sellador', 150)->nullable();
                $table->string('tecnica_obturacion', 150)->nullable();
                $table->string('estado', 30)->default('EN_TRATAMIENTO');
                $table->date('fecha');
                $table->text('observaciones')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['paciente_id', 'diente']);
                $table->index(['paciente_id', 'fecha']);
            });
        }

        // 2. Table trazados_cefalometricos (Ortodoncia)
        if (! Schema::hasTable('trazados_cefalometricos')) {
            Schema::create('trazados_cefalometricos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinica_id')->nullable()->constrained('clinicas')->cascadeOnDelete();
                $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
                $table->foreignId('doctor_id')->nullable()->constrained('doctores')->nullOnDelete();
                $table->foreignId('cita_id')->nullable()->constrained('citas')->nullOnDelete();
                $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->date('fecha');
                $table->string('tipo_analisis', 30)->default('STEINER');
                $table->string('imagen_radiografia')->nullable();
                $table->json('puntos')->nullable();
                $table->json('medidas')->nullable();
                $table->string('diagnostico_esqueletico', 50)->nullable();
                $table->string('patron_crecimiento', 50)->nullable();
                $table->text('interpretacion')->nullable();
                $table->text('plan_tratamiento')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['paciente_id', 'fecha']);
            });
        }

        // 3. Mejoras en odontogramas para Odontopediatría
        Schema::table('odontogramas', function (Blueprint $table) {
            if (! Schema::hasColumn('odontogramas', 'escala_frankl')) {
                $table->unsignedTinyInteger('escala_frankl')->nullable()->after('tipo');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trazados_cefalometricos');
        Schema::dropIfExists('conductometrias');
        Schema::table('odontogramas', function (Blueprint $table) {
            if (Schema::hasColumn('odontogramas', 'escala_frankl')) {
                $table->dropColumn('escala_frankl');
            }
        });
    }
};
