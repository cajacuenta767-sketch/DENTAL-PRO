<?php

namespace App\Http\Resources;

use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Pago */
class PagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo_recibo' => $this->codigo_recibo,
            'fecha_pago' => $this->fecha_pago?->toIso8601String(),
            'estado' => $this->estado,
            'metodo_pago' => $this->metodo_pago,
            'monto_total' => (float) $this->monto_total,
            'monto_pagado' => (float) $this->monto_pagado,
            'monto_saldo' => (float) $this->monto_saldo,
            'notas' => $this->notas,
            'paciente_id' => $this->paciente_id,
            'doctor_id' => $this->doctor_id,
            'cita_id' => $this->cita_id,
            'presupuesto_id' => $this->presupuesto_id,
            'sucursal_id' => $this->sucursal_id,
            'paciente' => new PacienteResource($this->whenLoaded('paciente')),
            'doctor' => new DoctorResource($this->whenLoaded('doctor')),
            'detalles' => PagoDetalleResource::collection($this->whenLoaded('detalles')),
            'creado_en' => $this->created_at?->toIso8601String(),
            'actualizado_en' => $this->updated_at?->toIso8601String(),
        ];
    }
}
