<?php

namespace App\Http\Resources;

use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Doctor */
class DoctorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'nombre_completo' => $this->nombre_completo,
            'nombre_profesional' => $this->nombre_profesional,
            'genero' => $this->genero,
            'telefono' => $this->telefono,
            'email' => $this->email,
            'colegiatura' => $this->colegiatura,
            'especialidad_id' => $this->especialidad_id,
            'especialidad' => new EspecialidadResource($this->whenLoaded('especialidad')),
            'activo' => (bool) $this->activo,
        ];
    }
}
