<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabla de Egresos de Caja Chica
        Schema::create('egresos_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->nullable()->constrained('clinicas')->nullOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('cierre_caja_id')->nullable()->constrained('cierres_caja')->nullOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->decimal('monto', 10, 2);
            $table->string('concepto', 255);
            $table->string('categoria', 50)->default('OTRO');
            $table->string('metodo_pago', 30)->default('EFECTIVO');
            $table->string('comprobante_tipo', 30)->nullable();
            $table->string('comprobante_numero', 100)->nullable();
            $table->string('comprobante_archivo', 255)->nullable();
            $table->date('fecha');
            $table->string('estado', 20)->default('REGISTRADO');
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinica_id', 'fecha']);
            $table->index(['sucursal_id', 'fecha']);
        });

        // 2. Modificaciones a cierres_caja para soportar turnos y egresos
        Schema::table('cierres_caja', function (Blueprint $table) {
            $table->string('turno', 20)->default('COMPLETO')->after('fecha');
            $table->decimal('total_egresos', 10, 2)->default(0)->after('diferencia');
            $table->decimal('efectivo_egresos', 10, 2)->default(0)->after('total_egresos');
            $table->time('hora_apertura')->nullable()->after('cerrado_en');
            $table->time('hora_cierre')->nullable()->after('hora_apertura');
        });

        // En PostgreSQL / MySQL se actualiza la restricción única para admitir turnos
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE cierres_caja DROP CONSTRAINT IF EXISTS cierres_caja_fecha_sucursal_id_unique');
            DB::statement('ALTER TABLE cierres_caja ADD CONSTRAINT cierres_caja_fecha_sucursal_turno_unique UNIQUE (fecha, sucursal_id, turno)');
        } elseif ($driver === 'mysql') {
            Schema::table('cierres_caja', function (Blueprint $table) {
                $table->dropUnique(['fecha', 'sucursal_id']);
                $table->unique(['fecha', 'sucursal_id', 'turno']);
            });
        }

        // 3. Soporte para pagos mixtos en tabla pagos
        Schema::table('pagos', function (Blueprint $table) {
            $table->json('desglose_metodos')->nullable()->after('metodo_pago');
        });

        // 4. Saldo a favor de pacientes para anticipos y pagos a cuenta
        Schema::table('pacientes', function (Blueprint $table) {
            $table->decimal('saldo_favor', 10, 2)->default(0)->after('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egresos_caja');

        Schema::table('pacientes', function (Blueprint $table) {
            $table->dropColumn('saldo_favor');
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn('desglose_metodos');
        });

        Schema::table('cierres_caja', function (Blueprint $table) {
            $table->dropColumn(['turno', 'total_egresos', 'efectivo_egresos', 'hora_apertura', 'hora_cierre']);
        });
    }
};
