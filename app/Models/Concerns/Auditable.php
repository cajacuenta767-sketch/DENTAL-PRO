<?php

namespace App\Models\Concerns;

use App\Models\Auditoria;

/**
 * Deja rastro en la tabla de auditoría de cada alta, cambio o baja del
 * modelo. Los atributos sensibles declarados en $auditarExcluir nunca se
 * guardan en el detalle de cambios.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($modelo) => $modelo->auditar('CREAR'));

        static::updated(function ($modelo) {
            $cambios = $modelo->cambiosAuditables();

            if ($cambios !== []) {
                $modelo->auditar('ACTUALIZAR', $cambios);
            }
        });

        static::deleted(function ($modelo) {
            $esLogico = method_exists($modelo, 'isForceDeleting') && ! $modelo->isForceDeleting();
            $modelo->auditar($esLogico ? 'ELIMINAR' : 'ELIMINAR');
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(fn ($modelo) => $modelo->auditar('RESTAURAR'));
        }
    }

    protected function auditar(string $accion, ?array $cambios = null): void
    {
        Auditoria::registrar($accion, $this, $this->descripcionAuditoria(), $cambios);
    }

    /** Texto corto que identifica el registro en el listado de auditoría. */
    public function descripcionAuditoria(): string
    {
        foreach (['codigo_recibo', 'codigo', 'folio', 'numero_control', 'token', 'nombre_completo', 'nombre', 'titulo', 'email'] as $campo) {
            $valor = $this->getAttribute($campo);

            if (filled($valor)) {
                return class_basename($this).' '.$valor;
            }
        }

        return class_basename($this).' #'.$this->getKey();
    }

    /** Pares antes/después de los atributos que cambiaron, sin los sensibles. */
    protected function cambiosAuditables(): array
    {
        $excluir = array_merge(
            ['updated_at', 'created_at', 'remember_token', 'password', 'codigo_2fa', 'codigo_2fa_expira_en', 'ultimo_acceso_en'],
            property_exists($this, 'auditarExcluir') ? $this->auditarExcluir : [],
        );

        $cambios = [];

        foreach ($this->getChanges() as $campo => $nuevo) {
            if (in_array($campo, $excluir, true)) {
                continue;
            }

            $cambios[$campo] = [
                'antes' => $this->normalizarValorAuditoria($this->getOriginal($campo)),
                'despues' => $this->normalizarValorAuditoria($nuevo),
            ];
        }

        return $cambios;
    }

    private function normalizarValorAuditoria(mixed $valor): mixed
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d H:i:s');
        }

        if (is_array($valor) || is_object($valor)) {
            return mb_substr(json_encode($valor, JSON_UNESCAPED_UNICODE) ?: '', 0, 500);
        }

        return is_string($valor) ? mb_substr($valor, 0, 500) : $valor;
    }
}
