<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insumos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 150);
            $table->string('descripcion', 255)->nullable();
            $table->enum('categoria', [
                'ANESTESIA', 'RESTAURACION', 'ENDODONCIA', 'ORTODONCIA', 'CIRUGIA',
                'PROFILAXIS', 'DESECHABLE', 'BIOSEGURIDAD', 'INSTRUMENTAL', 'OTRO',
            ])->default('OTRO');
            $table->string('unidad_medida', 20)->default('UNIDAD');
            $table->decimal('stock_actual', 10, 2)->default(0);
            $table->decimal('stock_minimo', 10, 2)->default(0);
            $table->decimal('costo_unitario', 10, 2)->default(0);
            $table->string('proveedor', 150)->nullable();
            $table->string('ubicacion', 100)->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['categoria', 'activo']);
        });

        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insumo_id')->constrained('insumos')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('cita_id')->nullable()->constrained('citas')->nullOnDelete();
            $table->enum('tipo', ['ENTRADA', 'SALIDA', 'AJUSTE', 'MERMA'])->default('ENTRADA');
            $table->decimal('cantidad', 10, 2);
            $table->decimal('stock_resultante', 10, 2);
            $table->decimal('costo_unitario', 10, 2)->default(0);
            $table->string('motivo', 255)->nullable();
            $table->string('referencia', 100)->nullable()->comment('Factura de compra, remisión o cita');
            $table->timestamp('fecha')->useCurrent();
            $table->timestamps();

            $table->index(['insumo_id', 'fecha']);
            $table->index(['tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
        Schema::dropIfExists('insumos');
    }
};
