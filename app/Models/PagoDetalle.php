<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoDetalle extends Model
{
    use Concerns\BelongsToClinica, HasFactory;

    protected $table = 'pago_detalles';

    protected $fillable = [
        'pago_id', 'tratamiento_id', 'descripcion', 'cantidad', 'precio_unitario', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'pago_id');
    }

    public function tratamiento(): BelongsTo
    {
        return $this->belongsTo(Tratamiento::class, 'tratamiento_id');
    }
}
