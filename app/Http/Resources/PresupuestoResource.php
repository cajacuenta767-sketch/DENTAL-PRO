<?php

namespace App\Http\Resources;

use App\Models\Presupuesto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Presupuesto */
class PresupuestoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'fecha' => $this->fecha?->toDateString(),
            'vence_el' => $this->fecha ? $this->vence_el->toDateString() : null,
            'validez_dias' => (int) $this->validez_dias,
            'estado' => $this->estado,
            'estado_legible' => $this->estado_legible,
            'subtotal' => (float) $this->subtotal,
            'descuento' => (float) $this->descuento,
            'cobertura_seguro' => (float) $this->cobertura_seguro,
            'porcentaje_cobertura' => $this->porcentaje_cobertura !== null ? (float) $this->porcentaje_cobertura : null,
            'total' => (float) $this->total,
            'notas' => $this->notas,
            'paciente_id' => $this->paciente_id,
            'doctor_id' => $this->doctor_id,
            'aseguradora_id' => $this->aseguradora_id,
            'paciente' => new PacienteResource($this->whenLoaded('paciente')),
            'doctor' => new DoctorResource($this->whenLoaded('doctor')),
            'avance' => $this->whenLoaded('detalles', fn () => $this->avance),
            'detalles' => PresupuestoDetalleResource::collection($this->whenLoaded('detalles')),
            'creado_en' => $this->created_at?->toIso8601String(),
            'actualizado_en' => $this->updated_at?->toIso8601String(),
        ];
    }
}
