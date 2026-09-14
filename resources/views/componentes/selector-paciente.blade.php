{{--
    Selector de paciente con búsqueda remota (Tom Select).
    Sólo renderiza la opción seleccionada: la lista se carga desde
    admin.pacientes.buscar a medida que el usuario escribe.

    <x-selector-paciente nombre="paciente_id" :seleccionado="$cita->paciente_id" requerido />
    Opcionales: :texto="Apellidos, Nombres · DOC" (evita la consulta) y :datos="['cobertura' => 30]" (atributos data-* de la opción).
--}}
@props([
    'nombre' => 'paciente_id',
    'seleccionado' => null,
    'texto' => null,
    'requerido' => false,
    'placeholder' => 'Escribe nombre o documento',
    'datos' => [],
])

@php
    $valor = old($nombre, $seleccionado);
    $datos = is_array($datos) ? $datos : [];

    // Si viene de old() (id distinto al preseleccionado) o no nos dieron el
    // texto, resolvemos la etiqueta con una consulta puntual.
    if (filled($valor) && (blank($texto) || (string) $valor !== (string) $seleccionado)) {
        $pacienteSeleccionado = \App\Models\Paciente::query()->with('aseguradora')->find($valor);

        if ($pacienteSeleccionado) {
            $texto = "{$pacienteSeleccionado->apellidos}, {$pacienteSeleccionado->nombres} · {$pacienteSeleccionado->numero_documento}";
            $datos = [
                'cobertura' => (float) ($pacienteSeleccionado->aseguradora?->porcentaje_cobertura ?? 0),
                'aseguradora' => $pacienteSeleccionado->aseguradora?->nombre ?? '',
            ];
        } else {
            $valor = null;
        }
    }
@endphp

<select id="{{ $nombre }}" name="{{ $nombre }}"
        data-selector-paciente
        data-url="{{ route('admin.pacientes.buscar') }}"
        data-placeholder="{{ $placeholder }}"
        @required($requerido)
        {{ $attributes->merge(['class' => 'form-select']) }}>
    <option value="">{{ $placeholder }}</option>
    @if (filled($valor))
        <option value="{{ $valor }}" selected
            @foreach ($datos as $clave => $dato) data-{{ $clave }}="{{ $dato }}" @endforeach>{{ $texto }}</option>
    @endif
</select>
