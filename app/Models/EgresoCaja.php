<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToClinica;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EgresoCaja extends Model
{
    use Auditable, BelongsToClinica, HasFactory, SoftDeletes;

    protected $table = 'egresos_caja';

    protected $fillable = [
        'clinica_id',
        'sucursal_id',
        'cierre_caja_id',
        'usuario_id',
        'monto',
        'concepto',
        'categoria',
        'metodo_pago',
        'comprobante_tipo',
        'comprobante_numero',
        'comprobante_archivo',
        'fecha',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    public const CATEGORIAS = [
        'INSUMOS_MENORES' => [
            'nombre' => 'Insumos Menores / Farmacia',
            'color' => 'blue',
            'icono' => 'ti-first-aid-kit',
        ],
        'SERVICIOS' => [
            'nombre' => 'Servicios y Mensajería',
            'color' => 'azure',
            'icono' => 'ti-truck-delivery',
        ],
        'MANTENIMIENTO' => [
            'nombre' => 'Mantenimiento y Reparaciones',
            'color' => 'orange',
            'icono' => 'ti-tools',
        ],
        'REFRIGERIO' => [
            'nombre' => 'Refrigerios e Higiene',
            'color' => 'teal',
            'icono' => 'ti-coffee',
        ],
        'VIATICOS' => [
            'nombre' => 'Viáticos y Movilidad',
            'color' => 'indigo',
            'icono' => 'ti-car',
        ],
        'OTRO' => [
            'nombre' => 'Otros Gastos Menores',
            'color' => 'secondary',
            'icono' => 'ti-dots',
        ],
    ];

    public const METODOS = ['EFECTIVO', 'TRANSFERENCIA', 'TARJETA'];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function cierre(): BelongsTo
    {
        return $this->belongsTo(CierreCaja::class, 'cierre_caja_id');
    }

    public function scopeVigentes($query)
    {
        return $query->where('estado', '!=', 'ANULADO');
    }

    public function getCategoriaLegibleAttribute(): string
    {
        return self::CATEGORIAS[$this->categoria]['nombre'] ?? $this->categoria;
    }

    public function getColorCategoriaAttribute(): string
    {
        return self::CATEGORIAS[$this->categoria]['color'] ?? 'secondary';
    }

    public function getIconoCategoriaAttribute(): string
    {
        return self::CATEGORIAS[$this->categoria]['icono'] ?? 'ti-dots';
    }
}
