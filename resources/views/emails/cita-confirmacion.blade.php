@component('mail::message')
# {{ $esRecordatorio ? '¡Te esperamos mañana!' : '¡Tu cita quedó registrada!' }}

Hola **{{ $cita->paciente->nombres }}**,

@if ($esRecordatorio)
Te recordamos que tienes una cita programada en {{ $clinica->nombre }}.
@else
Registramos tu cita en {{ $clinica->nombre }}. Estos son los datos:
@endif

@component('mail::panel')
**Fecha:** {{ $cita->fecha->format('d/m/Y') }} a las {{ substr($cita->hora, 0, 5) }}
**Doctor:** {{ $cita->doctor->nombre_profesional }} ({{ $cita->doctor->especialidad->nombre }})
**Tratamiento:** {{ $cita->tratamiento->nombre }}
**Duración estimada:** {{ $cita->tratamiento->duracion }} minutos
**Código de cita:** {{ $cita->token }}
@endcomponent

Presenta el código **{{ $cita->token }}** en recepción el día de tu atención.

@if ($clinica->direccion)
**Dirección:** {{ $clinica->direccion }}
@endif
@if ($clinica->telefono)
**Teléfono:** {{ $clinica->telefono }}
@endif

Si necesitas reprogramar o cancelar, comunícate con nosotros con anticipación.

Gracias,<br>
{{ $clinica->nombre }}
@endcomponent
