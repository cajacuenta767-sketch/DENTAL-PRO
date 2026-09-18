@extends('layouts.admin')

@section('pretitulo', 'Laboratorio')
@section('titulo', 'Orden de Laboratorio: ' . $orden->folio)

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.laboratorio.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i>Listado
        </a>
        <a href="{{ route('admin.laboratorio.pdf', $orden) }}" class="btn btn-outline-primary" target="_blank">
            <i class="ti ti-printer me-1"></i>Ticket de trabajo (PDF)
        </a>
        @can('laboratorio.editar')
            <a href="{{ route('admin.laboratorio.edit', $orden) }}" class="btn btn-primary">
                <i class="ti ti-edit me-1"></i>Editar
            </a>
        @endcan
        @can('laboratorio.eliminar')
            <form action="{{ route('admin.laboratorio.destroy', $orden) }}" method="POST" class="d-inline"
                  onsubmit="return confirm('¿Seguro que deseas eliminar esta orden de laboratorio permanentemente?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger">
                    <i class="ti ti-trash me-1"></i>Eliminar
                </button>
            </form>
        @endcan
    </div>
@endsection

@section('contenido')
{{-- Alerta médica del paciente si aplica --}}
@if ($orden->paciente)
    <div class="mb-3">
        <x-alerta-medica :paciente="$orden->paciente" />
    </div>
@endif

{{-- Pipeline de Progreso de la Orden --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-3">
                <div class="subheader">Estado actual</div>
                <div class="h2 mb-0">
                    <span class="badge bg-{{ $orden->color_badge }} fs-3">
                        {{ $orden->estado_legible }}
                    </span>
                </div>
            </div>
            <div class="col-md-9">
                <div class="steps steps-counter steps-lime">
                    @php
                        $claves = array_keys($estados);
                        $indiceActual = array_search($orden->estado, $claves);
                    @endphp
                    @foreach ($estados as $clave => $meta)
                        @php
                            $indiceEste = array_search($clave, $claves);
                            $claseStep = $indiceEste < $indiceActual ? 'step-item active' : ($indiceEste === $indiceActual ? 'step-item active current' : 'step-item');
                        @endphp
                        <span class="{{ $claseStep }}" title="{{ $meta['nombre'] }}">
                            <span class="step-counter">{{ $loop->iteration }}</span>
                            <span class="step-title small">{{ $meta['nombre'] }}</span>
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Botones de cambio rápido de estado --}}
        @can('laboratorio.editar')
            <div class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top">
                <span class="small fw-semibold text-secondary">Avanzar o cambiar estado:</span>
                @foreach ($estados as $clave => $meta)
                    @if ($clave !== $orden->estado)
                        <form action="{{ route('admin.laboratorio.estado', $orden) }}" method="POST" class="d-inline">
                            @csrf @method('PATCH')
                            <input type="hidden" name="estado" value="{{ $clave }}">
                            <button type="submit" class="btn btn-sm btn-outline-{{ $meta['color'] }}">
                                <i class="ti ti-arrow-right me-1"></i>{{ $meta['nombre'] }}
                            </button>
                        </form>
                    @endif
                @endforeach
            </div>
        @endcan
    </div>
</div>

<div class="row g-3">
    {{-- Columna izquierda: Ficha técnica del trabajo --}}
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-dental me-2"></i>Especificaciones del trabajo protésico</h3>
            </div>
            <div class="card-body">
                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Tipo de trabajo / Prótesis</div>
                        <div class="datagrid-content fw-bold fs-3 text-primary">{{ $orden->tipo_trabajo }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Guía de color VITA</div>
                        <div class="datagrid-content">
                            @if ($orden->color_guia)
                                <span class="badge bg-purple fs-3">{{ $orden->color_guia }}</span>
                            @else
                                <span class="text-secondary">No especificado</span>
                            @endif
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Piezas dentales involucradas (FDI)</div>
                        <div class="datagrid-content">
                            @if (!empty($orden->dientes_array))
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach ($orden->dientes_array as $pieza)
                                        <span class="badge bg-secondary-lt font-monospace fs-3 px-2 py-1">{{ $pieza }}</span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-secondary">Arcada completa / Sin especificar</span>
                            @endif
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Tratamiento del plan clínico</div>
                        <div class="datagrid-content">
                            {{ $orden->tratamiento?->nombre ?: 'Sin vincular a plan específico' }}
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <div class="subheader mb-2">Instrucciones técnicas del odontólogo</div>
                    <div class="p-3 bg-body-tertiary rounded border font-monospace" style="white-space: pre-wrap;">{{ $orden->notas_tecnicas ?: 'Sin indicaciones técnicas registradas.' }}</div>
                </div>
            </div>
        </div>

        {{-- Datos del Paciente y Doctor --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-user me-2"></i>Paciente y Odontólogo responsable</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="subheader">Paciente</div>
                        <h4 class="mb-1">
                            <a href="{{ route('admin.pacientes.show', $orden->paciente) }}" class="text-reset">
                                {{ $orden->paciente->nombre_completo }}
                            </a>
                        </h4>
                        <div class="small text-secondary">
                            <div><i class="ti ti-id me-1"></i>{{ $orden->paciente->tipo_documento }} {{ $orden->paciente->numero_documento }}</div>
                            <div><i class="ti ti-phone me-1"></i>{{ $orden->paciente->telefono ?: 'Sin teléfono' }}</div>
                            <div><i class="ti ti-mail me-1"></i>{{ $orden->paciente->email ?: 'Sin correo' }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="subheader">Doctor remitente</div>
                        <h4 class="mb-1">{{ $orden->doctor->nombre_profesional }}</h4>
                        <div class="small text-secondary">
                            <div><i class="ti ti-certificate me-1"></i>Matrícula: {{ $orden->doctor->matricula ?: '—' }}</div>
                            <div><i class="ti ti-stethoscope me-1"></i>{{ $orden->doctor->especialidades->pluck('nombre')->join(', ') ?: 'Odontología General' }}</div>
                            <div><i class="ti ti-phone me-1"></i>{{ $orden->doctor->telefono ?: '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Columna derecha: Laboratorio, Cronograma y Finanzas --}}
    <div class="col-lg-4">
        {{-- Laboratorio Protésico --}}
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-building-warehouse me-2"></i>Laboratorio Dental</h3>
            </div>
            <div class="card-body">
                @if ($orden->laboratorio)
                    <h4 class="mb-1">{{ $orden->laboratorio->nombre }}</h4>
                    <div class="small text-secondary mb-2">
                        @if ($orden->laboratorio->contacto)
                            <div><i class="ti ti-user-check me-1"></i>Contacto: {{ $orden->laboratorio->contacto }}</div>
                        @endif
                        @if ($orden->laboratorio->telefono)
                            <div><i class="ti ti-phone me-1"></i>Tel: {{ $orden->laboratorio->telefono }}</div>
                        @endif
                        @if ($orden->laboratorio->email)
                            <div><i class="ti ti-mail me-1"></i>Email: {{ $orden->laboratorio->email }}</div>
                        @endif
                        @if ($orden->laboratorio->direccion)
                            <div><i class="ti ti-map-pin me-1"></i>{{ $orden->laboratorio->direccion }}</div>
                        @endif
                    </div>
                @else
                    <div class="text-secondary">Laboratorio no asignado</div>
                @endif
            </div>
        </div>

        {{-- Cronograma de tiempos --}}
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-calendar me-2"></i>Cronograma</h3>
            </div>
            <div class="card-body">
                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Fecha de envío al laboratorio</div>
                        <div class="datagrid-content">{{ $orden->fecha_envio->format('d/m/Y') }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Fecha prometida de entrega</div>
                        <div class="datagrid-content">
                            @if ($orden->esta_vencida)
                                <span class="text-danger fw-bold">
                                    <i class="ti ti-alert-triangle me-1"></i>{{ $orden->fecha_prometida->format('d/m/Y') }} (Vencida)
                                </span>
                            @else
                                <span class="fw-medium">{{ $orden->fecha_prometida->format('d/m/Y') }}</span>
                                <small class="text-secondary d-block">{{ $orden->dias_restantes }} días restantes</small>
                            @endif
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Fecha real de entrega / Instalación</div>
                        <div class="datagrid-content">
                            {{ $orden->fecha_entrega ? $orden->fecha_entrega->format('d/m/Y') : 'Pendiente' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Costos y Rentabilidad --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-coins me-2"></i>Costos y Precios</h3>
            </div>
            <div class="card-body">
                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Costo del laboratorio</div>
                        <div class="datagrid-content fw-bold text-danger">
                            ${{ number_format((float) ($orden->costo_laboratorio ?? 0), 2) }}
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Precio cobrado al paciente</div>
                        <div class="datagrid-content fw-bold text-success">
                            ${{ number_format((float) ($orden->precio_paciente ?? 0), 2) }}
                        </div>
                    </div>
                    @if ($orden->precio_paciente && $orden->costo_laboratorio)
                        @php
                            $margen = (float)$orden->precio_paciente - (float)$orden->costo_laboratorio;
                            $porcentaje = $orden->precio_paciente > 0 ? round(($margen / $orden->precio_paciente) * 100, 1) : 0;
                        @endphp
                        <div class="datagrid-item">
                            <div class="datagrid-title">Margen clínico neto</div>
                            <div class="datagrid-content fw-bold text-primary">
                                ${{ number_format($margen, 2) }} ({{ $porcentaje }}%)
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
