<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Ajuste extends Model
{
    use Auditable, Concerns\BelongsToClinica;

    protected $table = 'ajustes';

    protected $fillable = [
        'clinica_id',
        'nombre', 'descripcion', 'direccion', 'telefono', 'email',
        'divisa', 'simbolo_divisa', 'nit', 'logo', 'web',
        'facebook', 'instagram', 'whatsapp',
        'minutos_intervalo_cita', 'horas_recordatorio', 'terminos_recibo',
        'reservas_online', 'reservas_token', 'reservas_anticipacion_dias',
        'reservas_minimo_horas', 'reservas_mensaje',
        'facturacion_serie', 'facturacion_tasa_iva', 'facturacion_activa',
        'recordatorio_canal', 'pagos_online_activos', 'portal_reservas_activas',
    ];

    protected function casts(): array
    {
        return [
            'reservas_online' => 'boolean',
            'reservas_anticipacion_dias' => 'integer',
            'reservas_minimo_horas' => 'integer',
            'facturacion_activa' => 'boolean',
            'facturacion_tasa_iva' => 'decimal:2',
            'pagos_online_activos' => 'boolean',
            'portal_reservas_activas' => 'boolean',
        ];
    }

    /** Configuración activa de la clínica (siempre existe una sola fila). */
    public static function actual(): self
    {
        $clinicaId = auth()->user()?->clinica_id;

        return static::query()->firstOrCreate(
            ['clinica_id' => $clinicaId],
            ['nombre' => 'OdontoSuite', 'divisa' => 'BOB', 'simbolo_divisa' => 'Bs']
        );
    }
}
