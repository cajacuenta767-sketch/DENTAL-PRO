<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('especialidad_id')->constrained('especialidades')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('nombres', 150);
            $table->string('apellidos', 150);
            $table->enum('tipo_documento', ['CI', 'DNI', 'PASAPORTE', 'CE'])->default('CI');
            $table->string('numero_documento', 20)->unique();
            $table->date('fecha_nacimiento')->nullable();
            $table->enum('genero', ['M', 'F', 'O'])->default('M');
            $table->string('telefono', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('direccion')->nullable();
            $table->string('colegiatura', 50)->nullable();
            $table->text('descripcion')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('fotografia', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['especialidad_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctores');
    }
};
