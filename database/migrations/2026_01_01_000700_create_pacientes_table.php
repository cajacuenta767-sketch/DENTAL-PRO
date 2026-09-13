<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pacientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('nombres', 150);
            $table->string('apellidos', 150);
            $table->enum('tipo_documento', ['CI', 'DNI', 'PASAPORTE', 'CE'])->default('CI');
            $table->string('numero_documento', 20)->unique();
            $table->date('fecha_nacimiento')->nullable();
            $table->enum('genero', ['M', 'F', 'O'])->default('M');
            $table->text('direccion')->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->enum('grupo_sanguineo', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])->nullable();
            $table->text('alergias')->nullable();
            $table->text('enfermedades')->nullable();
            $table->text('medicamentos')->nullable();
            $table->text('habitos')->nullable();
            $table->text('antecedentes')->nullable();
            $table->string('contacto_emergencia', 150)->nullable();
            $table->string('telefono_emergencia', 50)->nullable();
            $table->text('observaciones')->nullable();
            $table->string('fotografia', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['apellidos', 'nombres']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pacientes');
    }
};
