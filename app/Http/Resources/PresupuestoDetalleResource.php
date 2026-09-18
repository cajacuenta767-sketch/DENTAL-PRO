<?php

namespace App\Http\Resources;

use App\Models\PresupuestoDetalle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PresupuestoDetalle */
class PresupuestoDetalleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tratamiento_id' => $this->tratamiento_id,
            'tratamiento' => $this->whenLoaded('tratamiento', fn () => $this->tratamiento?->nombre),
            'descripcion' => $this->descripcion,
            'pieza_dental' => $this->pieza_dental,
            'cara' => $this->cara,
            'ubicacion' => $this->ubicacion,
            'cantidad' => (int) $this->cantidad,
            'precio_unitario' => (float) $this->precio_unitario,
            'subtotal' => (float) $this->subtotal,
            'estado' => $this->estado,
            'sesion' => $this->sesion,
            'orden' => $this->orden,
            'fecha_ejecucion' => $this->fecha_ejecucion?->toDateString(),
            'cita_id' => $this->cita_id,
            'pago_id' => $this->pago_id,
        ];
    }
}
