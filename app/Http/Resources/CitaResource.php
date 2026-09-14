<?php

namespace App\Http\Resources;

use App\Models\Cita;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Cita */
class CitaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'fecha' => $this->fecha?->toDateString(),
            'hora' => substr((string) $this->hora, 0, 5),
            'duracion_minutos' => $this->duracion_minutos,
            'estado' => $this->estado,
            'estado_legible' => $this->estado_legible,
            'origen' => $this->origen,
            'motivo' => $this->motivo,
            'observacion' => $this->observacion,
            'paciente_id' => $this->paciente_id,
            'doctor_id' => $this->doctor_id,
            'tratamiento_id' => $this->tratamiento_id,
            'sucursal_id' => $this->sucursal_id,
            'serie_id' => $this->serie_id,
            'confirmada_en' => $this->confirmada_en?->toIso8601String(),
            'paciente' => new PacienteResource($this->whenLoaded('paciente')),
            'doctor' => new DoctorResource($this->whenLoaded('doctor')),
            'tratamiento' => new TratamientoResource($this->whenLoaded('tratamiento')),
            'creado_en' => $this->created_at?->toIso8601String(),
            'actualizado_en' => $this->updated_at?->toIso8601String(),
        ];
    }
}
