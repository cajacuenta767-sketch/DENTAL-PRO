<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToClinica;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CicloEsterilizacion extends Model
{
    use Auditable, BelongsToClinica, HasFactory, SoftDeletes;

    protected $table = 'ciclos_esterilizacion';

    protected $fillable = [
        'clinica_id',
        'sucursal_id',
        'usuario_id',
        'autoclave_nombre',
        'numero_ciclo',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'temperatura',
        'presion',
        'tiempo_esterilizacion',
        'tipo_carga',
        'indicador_quimico',
        'indicador_biologico',
        'resultado',
        'qr_token',
        'paquetes_esterilizados',
        'fecha_caducidad_paquetes',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_caducidad_paquetes' => 'date',
            'temperatura' => 'decimal:1',
            'presion' => 'decimal:2',
            'tiempo_esterilizacion' => 'integer',
            'paquetes_esterilizados' => 'integer',
        ];
    }

    public const TIPOS_CARGA = [
        'INSTRUMENTAL_QUIRURGICO' => 'Instrumental Quirúrgico / Crítico',
        'ROTATORIOS' => 'Turbinas, Contraángulos y Piezas de mano',
        'TEXTILES_GASAS' => 'Gasas, Campos de tela y Apósitos',
        'MATERIAL_EMBOLSADO' => 'Exploración y Diagnóstico (Bolsa Grado Médico)',
    ];

    public const RESULTADOS = [
        'APROBADO' => 'Conforme / Aprobado para uso clínico',
        'RECHAZADO' => 'No conforme / Falla en ciclo (Repetir)',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function getEstaVigenteAttribute(): bool
    {
        return $this->resultado === 'APROBADO' && $this->fecha_caducidad_paquetes->isFuture();
    }

    public function getColorResultadoAttribute(): string
    {
        if ($this->resultado !== 'APROBADO') {
            return 'danger';
        }

        return $this->fecha_caducidad_paquetes->isPast() ? 'warning' : 'success';
    }
}
