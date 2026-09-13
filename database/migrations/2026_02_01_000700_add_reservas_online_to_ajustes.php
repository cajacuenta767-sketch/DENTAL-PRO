<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ajustes', function (Blueprint $table) {
            $table->boolean('reservas_online')->default(false)->after('horas_recordatorio');
            $table->string('reservas_token', 40)->nullable()->unique()->after('reservas_online');
            $table->unsignedSmallInteger('reservas_anticipacion_dias')->default(30)->after('reservas_token');
            $table->unsignedSmallInteger('reservas_minimo_horas')->default(4)->after('reservas_anticipacion_dias');
            $table->text('reservas_mensaje')->nullable()->after('reservas_minimo_horas');

            // Datos del emisor para la facturación electrónica.
            $table->string('facturacion_serie', 10)->default('A')->after('reservas_mensaje');
            $table->decimal('facturacion_tasa_iva', 5, 2)->default(13)->after('facturacion_serie');
            $table->boolean('facturacion_activa')->default(false)->after('facturacion_tasa_iva');
        });
    }

    public function down(): void
    {
        Schema::table('ajustes', function (Blueprint $table) {
            $table->dropColumn([
                'reservas_online', 'reservas_token', 'reservas_anticipacion_dias',
                'reservas_minimo_horas', 'reservas_mensaje',
                'facturacion_serie', 'facturacion_tasa_iva', 'facturacion_activa',
            ]);
        });
    }
};
