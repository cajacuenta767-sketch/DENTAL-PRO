<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Endurecimiento del sistema: integridad concurrente, auditoría, borrado
 * lógico, portal del paciente, lista de espera y consentimiento firmado.
 */
return new class extends Migration
{
    /** Tablas clínicas que pasan a borrado lógico. */
    private const CON_BORRADO_LOGICO = [
        'pacientes', 'doctores', 'historiales_clinicos', 'odontogramas', 'pagos',
        'presupuestos', 'documentos_clinicos', 'estudios_imagen', 'insumos',
    ];

    /** Claves foráneas sin índice (PostgreSQL no los crea automáticamente). */
    private const INDICES_FK = [
        'doctores' => ['usuario_id'],
        'pacientes' => ['usuario_id', 'aseguradora_id'],
        'citas' => ['paciente_id', 'tratamiento_id', 'estado', 'origen'],
        'historiales_clinicos' => ['doctor_id', 'cita_id'],
        'odontogramas' => ['doctor_id', 'cita_id'],
        'pagos' => ['paciente_id', 'doctor_id', 'cita_id', 'usuario_id'],
        'pago_detalles' => ['pago_id', 'tratamiento_id'],
        'estudios_imagen' => ['doctor_id', 'cita_id', 'usuario_id'],
        'documentos_clinicos' => ['doctor_id', 'cita_id', 'usuario_id'],
        'presupuestos' => ['doctor_id', 'usuario_id', 'odontograma_id'],
        'presupuesto_detalles' => ['tratamiento_id', 'cita_id'],
        'movimientos_inventario' => ['usuario_id', 'cita_id'],
        'documentos_fiscales' => ['pago_id', 'usuario_id'],
    ];

    public function up(): void
    {
        // --- Correlativos seguros ante concurrencia --------------------------
        Schema::create('secuencias', function (Blueprint $table) {
            $table->string('clave', 60)->primary();
            $table->unsignedBigInteger('valor')->default(0);
            $table->timestamps();
        });

        // --- Un cupo cancelado vuelve a estar disponible ---------------------
        Schema::table('citas', function (Blueprint $table) {
            $table->dropUnique('citas_doctor_slot_unico');
        });
        DB::statement(
            "CREATE UNIQUE INDEX citas_doctor_slot_unico ON citas (doctor_id, fecha, hora) WHERE estado <> 'CANCELADA'"
        );

        // --- Usuarios: primer acceso, doble factor, último acceso ------------
        Schema::table('usuarios', function (Blueprint $table) {
            $table->boolean('debe_cambiar_password')->default(false);
            $table->boolean('dos_factores')->default(false);
            $table->string('codigo_2fa', 255)->nullable();
            $table->timestamp('codigo_2fa_expira_en')->nullable();
            $table->timestamp('ultimo_acceso_en')->nullable();
        });

        // --- Borrado lógico ---------------------------------------------------
        foreach (self::CON_BORRADO_LOGICO as $tabla) {
            Schema::table($tabla, fn (Blueprint $table) => $table->softDeletes());
        }

        // --- Presupuestos: cobertura congelada y líneas cobradas -------------
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->foreignId('aseguradora_id')->nullable()->constrained('aseguradoras')->nullOnDelete();
            $table->decimal('porcentaje_cobertura', 5, 2)->nullable()->comment('Snapshot al aprobar');
        });
        Schema::table('presupuesto_detalles', function (Blueprint $table) {
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->nullOnDelete();
        });
        Schema::table('pagos', function (Blueprint $table) {
            $table->foreignId('presupuesto_id')->nullable()->constrained('presupuestos')->nullOnDelete();
        });

        // --- Facturación: notas de crédito y un solo documento vigente -------
        Schema::table('documentos_fiscales', function (Blueprint $table) {
            $table->foreignId('documento_referencia_id')->nullable()
                ->constrained('documentos_fiscales')->nullOnDelete()
                ->comment('Documento al que corrige una nota de crédito o débito');
        });
        DB::statement(
            'CREATE UNIQUE INDEX documentos_fiscales_pago_vigente ON documentos_fiscales (pago_id) '
            ."WHERE estado <> 'ANULADO' AND tipo IN ('FACTURA', 'CREDITO_FISCAL')"
        );

        // --- Consentimiento informado firmado --------------------------------
        Schema::table('documentos_clinicos', function (Blueprint $table) {
            $table->string('firma_archivo', 255)->nullable();
            $table->timestamp('firmado_en')->nullable();
        });

        // --- Auditoría --------------------------------------------------------
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('accion', 20);
            $table->string('modelo', 120)->nullable();
            $table->unsignedBigInteger('modelo_id')->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->json('cambios')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['modelo', 'modelo_id']);
            $table->index('usuario_id');
            $table->index('created_at');
            $table->index('accion');
        });

        // --- Lista de espera --------------------------------------------------
        Schema::create('lista_espera', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('doctores')->nullOnDelete();
            $table->foreignId('especialidad_id')->nullable()->constrained('especialidades')->nullOnDelete();
            $table->foreignId('tratamiento_id')->nullable()->constrained('tratamientos')->nullOnDelete();
            $table->foreignId('cita_id')->nullable()->constrained('citas')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->date('fecha_desde')->nullable();
            $table->date('fecha_hasta')->nullable();
            $table->enum('preferencia_turno', ['MAÑANA', 'TARDE', 'CUALQUIERA'])->default('CUALQUIERA');
            $table->enum('prioridad', ['BAJA', 'NORMAL', 'ALTA'])->default('NORMAL');
            $table->enum('estado', ['ESPERANDO', 'CONTACTADO', 'AGENDADO', 'CANCELADO'])->default('ESPERANDO');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['estado', 'prioridad']);
            $table->index('paciente_id');
            $table->index('doctor_id');
        });

        // --- Índices sobre claves foráneas y filtros frecuentes --------------
        foreach (self::INDICES_FK as $tabla => $columnas) {
            Schema::table($tabla, function (Blueprint $table) use ($columnas) {
                foreach ($columnas as $columna) {
                    $table->index($columna);
                }
            });
        }

        Schema::table('tratamientos', fn (Blueprint $table) => $table->unique(['especialidad_id', 'nombre']));
    }

    public function down(): void
    {
        Schema::table('tratamientos', fn (Blueprint $table) => $table->dropUnique(['especialidad_id', 'nombre']));

        foreach (self::INDICES_FK as $tabla => $columnas) {
            Schema::table($tabla, function (Blueprint $table) use ($columnas) {
                foreach ($columnas as $columna) {
                    $table->dropIndex([$columna]);
                }
            });
        }

        Schema::dropIfExists('lista_espera');
        Schema::dropIfExists('auditorias');

        Schema::table('documentos_clinicos', fn (Blueprint $table) => $table->dropColumn(['firma_archivo', 'firmado_en']));

        DB::statement('DROP INDEX IF EXISTS documentos_fiscales_pago_vigente');
        Schema::table('documentos_fiscales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('documento_referencia_id');
        });

        Schema::table('pagos', fn (Blueprint $table) => $table->dropConstrainedForeignId('presupuesto_id'));
        Schema::table('presupuesto_detalles', fn (Blueprint $table) => $table->dropConstrainedForeignId('pago_id'));
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aseguradora_id');
            $table->dropColumn('porcentaje_cobertura');
        });

        foreach (self::CON_BORRADO_LOGICO as $tabla) {
            Schema::table($tabla, fn (Blueprint $table) => $table->dropSoftDeletes());
        }

        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn(['debe_cambiar_password', 'dos_factores', 'codigo_2fa', 'codigo_2fa_expira_en', 'ultimo_acceso_en']);
        });

        DB::statement('DROP INDEX IF EXISTS citas_doctor_slot_unico');
        Schema::table('citas', fn (Blueprint $table) => $table->unique(['doctor_id', 'fecha', 'hora'], 'citas_doctor_slot_unico'));

        Schema::dropIfExists('secuencias');
    }
};
