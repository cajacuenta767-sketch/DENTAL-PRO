<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_fiscales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->enum('tipo', ['FACTURA', 'CREDITO_FISCAL', 'NOTA_CREDITO', 'NOTA_DEBITO'])->default('FACTURA');
            $table->string('serie', 10)->default('A');
            $table->unsignedInteger('correlativo');
            $table->string('numero_control', 40)->unique();
            $table->uuid('codigo_generacion')->unique();
            $table->string('receptor_nombre', 200);
            $table->string('receptor_documento', 40)->nullable();
            $table->string('receptor_direccion', 255)->nullable();
            $table->string('receptor_email', 150)->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('iva', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('tasa_iva', 5, 2)->default(13);
            $table->enum('estado', ['BORRADOR', 'EMITIDO', 'ANULADO'])->default('EMITIDO');
            $table->string('sello_recepcion', 100)->nullable();
            $table->json('contenido')->nullable()->comment('Snapshot del documento emitido');
            $table->text('motivo_anulacion')->nullable();
            $table->timestamp('fecha_emision');
            $table->timestamps();

            $table->unique(['tipo', 'serie', 'correlativo']);
            $table->index(['fecha_emision', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_fiscales');
    }
};
