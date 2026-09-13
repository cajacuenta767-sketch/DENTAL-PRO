<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('descripcion', 255)->nullable();
            $table->text('direccion')->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('divisa', 10)->default('BOB');
            $table->string('simbolo_divisa', 5)->default('Bs');
            $table->string('nit', 30)->nullable();
            $table->string('logo', 255)->nullable();
            $table->string('web', 255)->nullable();
            $table->string('facebook', 255)->nullable();
            $table->string('instagram', 255)->nullable();
            $table->string('whatsapp', 50)->nullable();
            $table->unsignedSmallInteger('minutos_intervalo_cita')->default(30);
            $table->unsignedSmallInteger('horas_recordatorio')->default(24);
            $table->text('terminos_recibo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes');
    }
};
