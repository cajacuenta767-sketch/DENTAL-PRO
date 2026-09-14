<?php

namespace App\Http\Resources;

use App\Models\PagoDetalle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PagoDetalle */
class PagoDetalleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tratamiento_id' => $this->tratamiento_id,
            'tratamiento' => $this->whenLoaded('tratamiento', fn () => $this->tratamiento?->nombre),
            'descripcion' => $this->descripcion,
            'cantidad' => (int) $this->cantidad,
            'precio_unitario' => (float) $this->precio_unitario,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
