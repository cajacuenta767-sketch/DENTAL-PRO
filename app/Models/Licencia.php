<?php

namespace App\Models;

use App\Services\LicenciaService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Estado de la licencia de ESTA instalación (siempre una sola fila).
 */
class Licencia extends Model
{
    protected $table = 'licencias';

    protected $fillable = [
        'codigo_instalacion', 'ancla', 'tipo', 'dia_fin', 'vence_en',
        'activada_en', 'renovada_en', 'historial',
    ];

    protected function casts(): array
    {
        return [
            'dia_fin' => 'integer',
            'vence_en' => 'date',
            'activada_en' => 'datetime',
            'renovada_en' => 'datetime',
            'historial' => 'array',
        ];
    }

    /** La licencia de esta instalación: la única fila, creada al primer uso. */
    public static function actual(): self
    {
        return static::query()->orderBy('id')->first()
            ?? static::create(['codigo_instalacion' => app(LicenciaService::class)->nuevoCodigoInstalacion()]);
    }

    public function estaActivada(): bool
    {
        return $this->ancla !== null && $this->vence_en !== null;
    }

    public function esVitalicia(): bool
    {
        return $this->estaActivada() && app(LicenciaService::class)->esVitalicio($this->dia_fin);
    }

    public function estaVigente(): bool
    {
        return $this->estaActivada()
            && ($this->esVitalicia() || $this->vence_en->endOfDay()->isFuture());
    }

    /** Días completos que faltan para el vencimiento (0 si vence hoy, negativo si venció). */
    public function diasRestantes(): int
    {
        if (! $this->estaActivada()) {
            return 0;
        }

        return (int) CarbonImmutable::today()->diffInDays($this->vence_en, false);
    }

    public function getEstadoAttribute(): string
    {
        return match (true) {
            ! $this->estaActivada() => 'SIN ACTIVAR',
            $this->estaVigente() => 'ACTIVA',
            default => 'VENCIDA',
        };
    }

    public function getTipoEtiquetaAttribute(): string
    {
        return match (true) {
            ! $this->estaActivada() => '—',
            $this->esVitalicia() => 'Licencia vitalicia',
            $this->tipo === 'PRUEBA' => 'Prueba gratuita',
            default => 'Licencia completa',
        };
    }

    /** Anota un evento en el historial sin pisar los anteriores. */
    public function registrar(string $evento, array $datos = []): void
    {
        $historial = $this->historial ?? [];
        $historial[] = ['evento' => $evento, 'fecha' => now()->toDateTimeString()] + $datos;
        $this->historial = array_slice($historial, -50);
    }
}
