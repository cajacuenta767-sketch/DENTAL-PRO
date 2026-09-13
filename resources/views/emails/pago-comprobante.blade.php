@component('mail::message')
# Comprobante de pago

Hola **{{ $pago->paciente->nombres }}**,

Adjuntamos el comprobante **{{ $pago->codigo_recibo }}** emitido por {{ $clinica->nombre }}.

@component('mail::table')
| Concepto | Cant. | Precio | Subtotal |
|:---------|:-----:|-------:|---------:|
@foreach ($pago->detalles as $detalle)
| {{ $detalle->descripcion }} | {{ $detalle->cantidad }} | {{ number_format($detalle->precio_unitario, 2) }} | {{ number_format($detalle->subtotal, 2) }} |
@endforeach
@endcomponent

@component('mail::panel')
**Total:** {{ number_format($pago->monto_total, 2) }} {{ $clinica->divisa }}
**Pagado:** {{ number_format($pago->monto_pagado, 2) }} {{ $clinica->divisa }}
**Saldo:** {{ number_format($pago->monto_saldo, 2) }} {{ $clinica->divisa }}
**Método:** {{ $pago->metodo_pago }}
@endcomponent

@if ($clinica->terminos_recibo)
{{ $clinica->terminos_recibo }}
@endif

Gracias por tu confianza,<br>
{{ $clinica->nombre }}
@endcomponent
