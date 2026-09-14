<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Rastro de quién hizo qué. Cada modelo con el trait Auditable registra sus
 * altas, cambios y bajas; los controladores de acceso registran sesiones.
 */
class Auditoria extends Model
{
    protected $table = 'auditorias';

    public $timestamps = false;

    protected $fillable = [
        'usuario_id', 'accion', 'modelo', 'modelo_id', 'descripcion', 'cambios', 'ip', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'cambios' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public const ACCIONES = [
        'CREAR' => 'Creó',
        'ACTUALIZAR' => 'Actualizó',
        'ELIMINAR' => 'Eliminó',
        'RESTAURAR' => 'Restauró',
        'INGRESO' => 'Inició sesión',
        'SALIDA' => 'Cerró sesión',
        'ACCESO_FALLIDO' => 'Intento fallido',
        'ANULAR' => 'Anuló',
        'EXPORTAR' => 'Exportó',
    ];

    public const COLORES = [
        'CREAR' => 'success',
        'ACTUALIZAR' => 'azure',
        'ELIMINAR' => 'danger',
        'RESTAURAR' => 'teal',
        'INGRESO' => 'primary',
        'SALIDA' => 'secondary',
        'ACCESO_FALLIDO' => 'warning',
        'ANULAR' => 'orange',
        'EXPORTAR' => 'indigo',
    ];

    /** Cuando está en falso (seeders masivos) no se escribe nada. */
    public static bool $activa = true;

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** Registra un evento; nunca interrumpe la operación auditada. */
    public static function registrar(
        string $accion,
        ?Model $modelo = null,
        ?string $descripcion = null,
        ?array $cambios = null,
        ?int $usuarioId = null,
    ): ?self {
        if (! static::$activa) {
            return null;
        }

        try {
            $request = app()->bound('request') ? request() : null;

            return static::query()->create([
                'usuario_id' => $usuarioId ?? Auth::id(),
                'accion' => $accion,
                'modelo' => $modelo ? class_basename($modelo) : null,
                'modelo_id' => $modelo?->getKey(),
                'descripcion' => $descripcion ? mb_substr($descripcion, 0, 255) : null,
                'cambios' => $cambios ?: null,
                'ip' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function getAccionLegibleAttribute(): string
    {
        return self::ACCIONES[$this->accion] ?? $this->accion;
    }

    public function getColorAttribute(): string
    {
        return self::COLORES[$this->accion] ?? 'secondary';
    }

    /** Nombre en español del modelo auditado, para los filtros y el listado. */
    public function getModeloLegibleAttribute(): string
    {
        return self::MODELOS[$this->modelo] ?? (string) $this->modelo;
    }

    public const MODELOS = [
        'Paciente' => 'Paciente',
        'Doctor' => 'Doctor',
        'Cita' => 'Cita',
        'Pago' => 'Recibo',
        'Presupuesto' => 'Presupuesto',
        'DocumentoFiscal' => 'Documento fiscal',
        'DocumentoClinico' => 'Documento clínico',
        'HistorialClinico' => 'Historia clínica',
        'Odontograma' => 'Odontograma',
        'EstudioImagen' => 'Estudio de imagen',
        'Usuario' => 'Usuario',
        'Ajuste' => 'Ajustes',
        'Insumo' => 'Insumo',
        'MovimientoInventario' => 'Movimiento de inventario',
        'Aseguradora' => 'Aseguradora',
        'Horario' => 'Horario',
        'Tratamiento' => 'Tratamiento',
        'ListaEspera' => 'Lista de espera',
    ];
}
