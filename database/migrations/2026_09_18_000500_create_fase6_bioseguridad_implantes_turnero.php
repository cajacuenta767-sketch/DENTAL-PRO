<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Table ciclos_esterilizacion (Bioseguridad Autoclave)
        if (!Schema::hasTable('ciclos_esterilizacion')) {
            Schema::create('ciclos_esterilizacion', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinica_id')->nullable()->constrained('clinicas')->cascadeOnDelete();
                $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
                $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->string('autoclave_nombre', 100)->default('Autoclave Principal Clase B');
                $table->integer('numero_ciclo');
                $table->date('fecha');
                $table->string('hora_inicio', 10)->nullable();
                $table->string('hora_fin', 10)->nullable();
                $table->decimal('temperatura', 5, 1)->default(134.0); // °C
                $table->decimal('presion', 4, 2)->default(2.10); // bar
                $table->integer('tiempo_esterilizacion')->default(18); // minutos
                $table->string('tipo_carga', 50)->default('INSTRUMENTAL_QUIRURGICO');
                $table->string('indicador_quimico', 20)->default('CONFORME');
                $table->string('indicador_biologico', 20)->default('NEGATIVO');
                $table->string('resultado', 20)->default('APROBADO'); // APROBADO, RECHAZADO
                $table->string('qr_token', 64)->unique();
                $table->integer('paquetes_esterilizados')->default(1);
                $table->date('fecha_caducidad_paquetes');
                $table->text('observaciones')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['fecha', 'resultado']);
            });
        }

        // 2. Table implantes_paciente (Pasaporte Digital de Implantes)
        if (!Schema::hasTable('implantes_paciente')) {
            Schema::create('implantes_paciente', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinica_id')->nullable()->constrained('clinicas')->cascadeOnDelete();
                $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
                $table->foreignId('doctor_id')->nullable()->constrained('doctores')->nullOnDelete();
                $table->foreignId('cita_id')->nullable()->constrained('citas')->nullOnDelete();
                $table->integer('posicion_fdi'); // Pieza FDI ej. 16, 21, 36, 46
                $table->string('marca', 100);
                $table->string('modelo', 100);
                $table->string('numero_lote', 100);
                $table->string('numero_serie', 100)->nullable();
                $table->decimal('diametro_mm', 4, 2);
                $table->decimal('longitud_mm', 4, 2);
                $table->string('tipo_conexion', 50)->default('CONO_MORSE');
                $table->decimal('torque_insercion_ncm', 5, 1)->nullable();
                $table->integer('isq_estabilidad')->nullable();
                $table->string('injerto_oseo', 150)->nullable();
                $table->string('membrana', 150)->nullable();
                $table->date('fecha_colocacion');
                $table->date('fecha_rehabilitacion')->nullable();
                $table->string('qr_pasaporte_token', 64)->unique();
                $table->string('estado', 30)->default('COLOCADO'); // COLOCADO, OSTEOINTEGRADO, REHABILITADO, FALLIDO
                $table->text('observaciones')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['paciente_id', 'posicion_fdi']);
            });
        }

        // 3. Campos en citas para Turnero TV y CRM Post-Op
        Schema::table('citas', function (Blueprint $table) {
            if (!Schema::hasColumn('citas', 'llamado_en')) {
                $table->timestamp('llamado_en')->nullable()->after('observacion');
            }
            if (!Schema::hasColumn('citas', 'consultorio')) {
                $table->string('consultorio', 50)->nullable()->after('llamado_en');
            }
            if (!Schema::hasColumn('citas', 'postop_contactado_en')) {
                $table->timestamp('postop_contactado_en')->nullable()->after('consultorio');
            }
            if (!Schema::hasColumn('citas', 'postop_estado')) {
                $table->string('postop_estado', 30)->nullable()->after('postop_contactado_en');
            }
        });
    }

    public function down(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            if (Schema::hasColumn('citas', 'postop_estado')) $table->dropColumn('postop_estado');
            if (Schema::hasColumn('citas', 'postop_contactado_en')) $table->dropColumn('postop_contactado_en');
            if (Schema::hasColumn('citas', 'consultorio')) $table->dropColumn('consultorio');
            if (Schema::hasColumn('citas', 'llamado_en')) $table->dropColumn('llamado_en');
        });
        Schema::dropIfExists('implantes_paciente');
        Schema::dropIfExists('ciclos_esterilizacion');
    }
};
