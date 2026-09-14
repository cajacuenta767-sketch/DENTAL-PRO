<?php

namespace App\Http\Resources;

use App\Models\Tratamiento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tratamiento */
class TratamientoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'precio' => (float) $this->precio,
            'duracion' => (int) $this->duracion,
            'especialidad_id' => $this->especialidad_id,
            'especialidad' => new EspecialidadResource($this->whenLoaded('especialidad')),
            'activo' => (bool) $this->activo,
        ];
    }
}
