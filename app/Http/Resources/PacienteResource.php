<?php

namespace App\Http\Resources;

use App\Models\Paciente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Paciente */
class PacienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'nombre_completo' => $this->nombre_completo,
            'tipo_documento' => $this->tipo_documento,
            'numero_documento' => $this->numero_documento,
            'fecha_nacimiento' => $this->fecha_nacimiento?->toDateString(),
            'edad' => $this->edad,
            'genero' => $this->genero,
            'telefono' => $this->telefono,
            'email' => $this->email,
            'direccion' => $this->direccion,
            'grupo_sanguineo' => $this->grupo_sanguineo,
            'alergias' => $this->alergias,
            'enfermedades' => $this->enfermedades,
            'medicamentos' => $this->medicamentos,
            'habitos' => $this->habitos,
            'antecedentes' => $this->antecedentes,
            'contacto_emergencia' => $this->contacto_emergencia,
            'telefono_emergencia' => $this->telefono_emergencia,
            'observaciones' => $this->observaciones,
            'numero_afiliado' => $this->numero_afiliado,
            'aseguradora' => $this->whenLoaded('aseguradora', fn () => $this->aseguradora ? [
                'id' => $this->aseguradora->id,
                'nombre' => $this->aseguradora->nombre,
                'porcentaje_cobertura' => $this->aseguradora->porcentaje_cobertura !== null
                    ? (float) $this->aseguradora->porcentaje_cobertura
                    : null,
            ] : null),
            'activo' => (bool) $this->activo,
            'creado_en' => $this->created_at?->toIso8601String(),
            'actualizado_en' => $this->updated_at?->toIso8601String(),
        ];
    }
}
