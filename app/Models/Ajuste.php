<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ajuste extends Model
{
    protected $table = 'ajustes';

    protected $fillable = [
        'nombre', 'descripcion', 'direccion', 'telefono', 'email',
        'divisa', 'simbolo_divisa', 'nit', 'logo', 'web',
        'facebook', 'instagram', 'whatsapp',
        'minutos_intervalo_cita', 'horas_recordatorio', 'terminos_recibo',
        'reservas_online', 'reservas_token', 'reservas_anticipacion_dias',
        'reservas_minimo_horas', 'reservas_mensaje',
        'facturacion_serie', 'facturacion_tasa_iva', 'facturacion_activa',
    ];

    protected function casts(): array
    {
        return [
            'reservas_online' => 'boolean',
            'reservas_anticipacion_dias' => 'integer',
            'reservas_minimo_horas' => 'integer',
            'facturacion_activa' => 'boolean',
            'facturacion_tasa_iva' => 'decimal:2',
        ];
    }

    /** Configuración activa de la clínica (siempre existe una sola fila). */
    public static function actual(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            ['nombre' => 'OdontoSuite', 'divisa' => 'BOB', 'simbolo_divisa' => 'Bs']
        );
    }
}
