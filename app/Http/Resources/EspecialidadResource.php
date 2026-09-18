<?php

namespace App\Http\Resources;

use App\Models\Especialidad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Especialidad */
class EspecialidadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'color' => $this->color,
            'activo' => (bool) $this->activo,
        ];
    }
}
