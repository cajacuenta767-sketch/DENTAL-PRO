<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Segunda ampliación: sucursales, periodontograma, sesiones del plan de
 * tratamiento, evolución clínica, anotaciones de imagenología, citas
 * recurrentes y confirmación por enlace, caja diaria, comisiones, pagos en
 * línea, transmisión fiscal y búsqueda por trigramas.
 */
return new class extends Migration
{
    /** Columnas de texto que se buscan con ilike y reciben índice GIN por trigramas. */
    private const TRIGRAMAS = [
        'pacientes' => ['nombres', 'apellidos', 'numero_documento', 'telefono', 'email'],
        'doctores' => ['nombres', 'apellidos'],
        'insumos' => ['nombre', 'codigo'],
        'citas' => ['token'],
        'pagos' => ['codigo_recibo'],
        'presupuestos' => ['codigo'],
        'documentos_clinicos' => ['folio', 'titulo'],
        'documentos_fiscales' => ['numero_control', 'receptor_nombre'],
        'auditorias' => ['descripcion'],
    ];

    public function up(): void
    {
        // --- Sucursales -------------------------------------------------------
        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('codigo', 10)->unique();
            $table->text('direccion')->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('color', 7)->default('#0d9488');
            $table->boolean('principal')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        foreach (['usuarios', 'horarios', 'citas', 'pagos', 'lista_espera', 'insumos'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            });
        }

        // --- Periodontograma ------------------------------------------------
        Schema::create('periodontogramas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('doctores')->nullOnDelete();
            $table->foreignId('cita_id')->nullable()->constrained('citas')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->date('fecha');
            $table->json('piezas')->comment('Por pieza: sondaje, sangrado, placa, recesion, movilidad, furca, ausente');
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['paciente_id', 'fecha']);
            $table->index('doctor_id');
            $table->index('cita_id');
        });

        // --- Plan de tratamiento por sesiones -------------------------------
        Schema::table('presupuesto_detalles', function (Blueprint $table) {
            $table->unsignedSmallInteger('sesion')->default(1)->comment('Sesión planificada del plan');
        });

        // --- Evolución clínica más completa ---------------------------------
        Schema::table('historiales_clinicos', function (Blueprint $table) {
            $table->string('plantilla', 60)->nullable();
            $table->string('anestesia', 120)->nullable();
            $table->string('anestesia_cantidad', 40)->nullable();
            $table->text('medicacion')->nullable();
            $table->text('proxima_cita_indicaciones')->nullable();
        });

        // --- Anotaciones sobre estudios de imagen ---------------------------
        Schema::table('estudios_imagen', function (Blueprint $table) {
            $table->json('anotaciones')->nullable()->comment('Trazos y textos dibujados sobre la imagen');
        });

        // --- Citas recurrentes y confirmación por enlace --------------------
        Schema::table('citas', function (Blueprint $table) {
            $table->uuid('serie_id')->nullable()->index()->comment('Agrupa las citas de una serie recurrente');
            $table->string('confirmacion_token', 64)->nullable()->unique();
            $table->timestamp('confirmada_en')->nullable();
            $table->string('confirmada_por', 20)->nullable()->comment('paciente, recepcion, doctor');
            $table->string('recordatorio_canal', 20)->nullable();
        });

        Schema::table('ajustes', function (Blueprint $table) {
            $table->string('recordatorio_canal', 20)->default('correo')->comment('correo, whatsapp, sms, correo_whatsapp');
            $table->boolean('pagos_online_activos')->default(false);
            $table->boolean('portal_reservas_activas')->default(true);
        });

        // --- Caja diaria ---------------------------------------------------
        Schema::create('cierres_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->date('fecha');
            $table->decimal('fondo_inicial', 10, 2)->default(0);
            $table->decimal('efectivo_esperado', 10, 2)->default(0);
            $table->decimal('efectivo_contado', 10, 2)->default(0);
            $table->decimal('diferencia', 10, 2)->default(0);
            $table->decimal('total_cobrado', 10, 2)->default(0);
            $table->unsignedInteger('recibos')->default(0);
            $table->json('totales')->nullable()->comment('Desglose por método de pago y por cajero');
            $table->text('observaciones')->nullable();
            $table->enum('estado', ['ABIERTO', 'CERRADO'])->default('CERRADO');
            $table->timestamp('cerrado_en')->nullable();
            $table->timestamps();

            $table->unique(['fecha', 'sucursal_id']);
            $table->index('usuario_id');
        });

        // --- Comisiones por doctor ------------------------------------------
        Schema::table('doctores', function (Blueprint $table) {
            $table->decimal('porcentaje_comision', 5, 2)->default(0);
        });

        // --- Pagos en línea ---------------------------------------------------
        Schema::create('pagos_online', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->string('proveedor', 30);
            $table->string('referencia', 120)->nullable()->index();
            $table->decimal('monto', 10, 2);
            $table->string('moneda', 10)->default('BOB');
            $table->enum('estado', ['PENDIENTE', 'PAGADO', 'FALLIDO', 'CANCELADO'])->default('PENDIENTE');
            $table->string('url', 500)->nullable();
            $table->json('respuesta')->nullable();
            $table->timestamp('pagado_en')->nullable();
            $table->timestamps();

            $table->index(['pago_id', 'estado']);
        });

        // --- Transmisión del documento fiscal --------------------------------
        Schema::table('documentos_fiscales', function (Blueprint $table) {
            $table->string('proveedor', 30)->nullable();
            $table->enum('estado_transmision', ['NO_APLICA', 'PENDIENTE', 'ACEPTADO', 'RECHAZADO'])->default('NO_APLICA');
            $table->json('respuesta_proveedor')->nullable();
            $table->timestamp('transmitido_en')->nullable();
        });

        // --- Búsqueda por trigramas (opcional: requiere pg_trgm) ------------
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

            foreach (self::TRIGRAMAS as $tabla => $columnas) {
                foreach ($columnas as $columna) {
                    DB::statement("CREATE INDEX IF NOT EXISTS {$tabla}_{$columna}_trgm ON {$tabla} USING gin ({$columna} gin_trgm_ops)");
                }
            }
        } catch (\Throwable $e) {
            // Sin permisos para crear la extensión: las búsquedas siguen funcionando, solo sin índice.
            report($e);
        }
    }

    public function down(): void
    {
        foreach (self::TRIGRAMAS as $tabla => $columnas) {
            foreach ($columnas as $columna) {
                DB::statement("DROP INDEX IF EXISTS {$tabla}_{$columna}_trgm");
            }
        }

        Schema::table('documentos_fiscales', fn (Blueprint $table) => $table->dropColumn(['proveedor', 'estado_transmision', 'respuesta_proveedor', 'transmitido_en']));
        Schema::dropIfExists('pagos_online');
        Schema::table('doctores', fn (Blueprint $table) => $table->dropColumn('porcentaje_comision'));
        Schema::dropIfExists('cierres_caja');
        Schema::table('ajustes', fn (Blueprint $table) => $table->dropColumn(['recordatorio_canal', 'pagos_online_activos', 'portal_reservas_activas']));
        Schema::table('citas', fn (Blueprint $table) => $table->dropColumn(['serie_id', 'confirmacion_token', 'confirmada_en', 'confirmada_por', 'recordatorio_canal']));
        Schema::table('estudios_imagen', fn (Blueprint $table) => $table->dropColumn('anotaciones'));
        Schema::table('historiales_clinicos', function (Blueprint $table) {
            $table->dropColumn(['plantilla', 'anestesia', 'anestesia_cantidad', 'medicacion', 'proxima_cita_indicaciones']);
        });
        Schema::table('presupuesto_detalles', fn (Blueprint $table) => $table->dropColumn('sesion'));
        Schema::dropIfExists('periodontogramas');

        foreach (['usuarios', 'horarios', 'citas', 'pagos', 'lista_espera', 'insumos'] as $tabla) {
            Schema::table($tabla, fn (Blueprint $table) => $table->dropConstrainedForeignId('sucursal_id'));
        }

        Schema::dropIfExists('sucursales');
    }
};
