<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Intento de pago de un saldo desde el portal a través de una pasarela. */
class PagoOnline extends Model
{
    use Concerns\BelongsToClinica;

    protected $table = 'pagos_online';

    protected $fillable = [
        'clinica_id', 'pago_id', 'paciente_id', 'proveedor', 'referencia', 'monto', 'moneda',
        'estado', 'url', 'respuesta', 'pagado_en',
    ];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2', 'respuesta' => 'array', 'pagado_en' => 'datetime'];
    }

    public const ESTADOS = ['PENDIENTE', 'PAGADO', 'FALLIDO', 'CANCELADO'];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'pago_id');
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }
}
