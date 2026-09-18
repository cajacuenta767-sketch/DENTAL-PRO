<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToClinica
{
    public static function bootBelongsToClinica(): void
    {
        static::addGlobalScope('clinica', function (Builder $query): void {
            $usuario = auth()->user();
            if ($usuario?->clinica_id && ! $usuario->hasRole('SUPER ADMINISTRADOR')) {
                $query->where($query->qualifyColumn('clinica_id'), $usuario->clinica_id);
            }
        });

        static::creating(function ($modelo): void {
            $usuario = auth()->user();
            if (! $modelo->clinica_id && $usuario?->clinica_id) {
                $modelo->clinica_id = $usuario->clinica_id;
            }
        });
    }
}
