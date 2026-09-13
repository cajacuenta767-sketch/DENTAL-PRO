<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aseguradoras', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150)->unique();
            $table->string('codigo', 30)->nullable();
            $table->enum('tipo', ['PRIVADA', 'PUBLICA', 'PREPAGA', 'CONVENIO'])->default('PRIVADA');
            $table->decimal('porcentaje_cobertura', 5, 2)->default(0)->comment('Porcentaje que cubre sobre el presupuesto');
            $table->decimal('tope_anual', 10, 2)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('contacto', 150)->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('pacientes', function (Blueprint $table) {
            $table->foreignId('aseguradora_id')->nullable()->after('usuario_id')
                ->constrained('aseguradoras')->nullOnDelete();
            $table->string('numero_afiliado', 60)->nullable()->after('aseguradora_id');
        });
    }

    public function down(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aseguradora_id');
            $table->dropColumn('numero_afiliado');
        });

        Schema::dropIfExists('aseguradoras');
    }
};
