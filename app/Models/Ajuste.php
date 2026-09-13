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
    ];

    /** Configuración activa de la clínica (siempre existe una sola fila). */
    public static function actual(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            ['nombre' => 'OdontoSuite', 'divisa' => 'BOB', 'simbolo_divisa' => 'Bs']
        );
    }
}
