<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinicas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 160);
            $table->string('slug', 180)->unique();
            $table->enum('estado', ['ACTIVA', 'SUSPENDIDA'])->default('ACTIVA');
            $table->string('plan', 60)->default('PRUEBA');
            $table->timestamp('vence_en')->nullable();
            $table->timestamps();
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->foreignId('clinica_id')->nullable()->after('id')->constrained('clinicas')->nullOnDelete()->index();
        });
        Schema::table('pacientes', function (Blueprint $table) {
            $table->foreignId('clinica_id')->nullable()->after('id')->constrained('clinicas')->nullOnDelete()->index();
        });
        Schema::table('sucursales', function (Blueprint $table) {
            $table->foreignId('clinica_id')->nullable()->after('id')->constrained('clinicas')->cascadeOnDelete()->index();
            $table->unique(['clinica_id', 'codigo']);
        });

        foreach ([
            'ajustes', 'especialidades', 'doctores', 'horarios', 'tratamientos',
            'citas', 'historiales_clinicos', 'odontogramas', 'pagos', 'pago_detalles',
            'aseguradoras', 'insumos', 'movimientos_inventario', 'estudios_imagen',
            'documentos_clinicos', 'presupuestos', 'presupuesto_detalles',
            'documentos_fiscales', 'secuencias', 'auditorias', 'lista_espera',
            'periodontogramas', 'cierres_caja', 'pagos_online',
        ] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->foreignId('clinica_id')->nullable()->constrained('clinicas')->cascadeOnDelete()->index();
            });
        }

        Schema::create('invitaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->nullable()->constrained('clinicas')->cascadeOnDelete();
            $table->foreignId('creada_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('usada_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('email', 150)->nullable();
            $table->string('rol', 50);
            $table->char('codigo_hash', 64)->unique();
            $table->string('codigo_visible', 20);
            $table->unsignedSmallInteger('usos_maximos')->default(1);
            $table->unsignedSmallInteger('usos')->default(0);
            $table->timestamp('vence_en')->nullable();
            $table->timestamp('usada_en')->nullable();
            $table->boolean('activa')->default(true);
            $table->json('datos')->nullable();
            $table->timestamps();
            $table->index(['activa', 'vence_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitaciones');
        foreach ([
            'ajustes', 'especialidades', 'doctores', 'horarios', 'tratamientos',
            'citas', 'historiales_clinicos', 'odontogramas', 'pagos', 'pago_detalles',
            'aseguradoras', 'insumos', 'movimientos_inventario', 'estudios_imagen',
            'documentos_clinicos', 'presupuestos', 'presupuesto_detalles',
            'documentos_fiscales', 'secuencias', 'auditorias', 'lista_espera',
            'periodontogramas', 'cierres_caja', 'pagos_online',
        ] as $tabla) {
            Schema::table($tabla, fn (Blueprint $table) => $table->dropConstrainedForeignId('clinica_id'));
        }
        Schema::table('sucursales', fn (Blueprint $table) => $table->dropConstrainedForeignId('clinica_id'));
        Schema::table('pacientes', fn (Blueprint $table) => $table->dropConstrainedForeignId('clinica_id'));
        Schema::table('usuarios', fn (Blueprint $table) => $table->dropConstrainedForeignId('clinica_id'));
        Schema::dropIfExists('clinicas');
    }
};
