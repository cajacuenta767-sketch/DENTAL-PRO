@extends('layouts.admin')

@section('pretitulo', 'Validación Chairside')
@section('titulo', 'Certificado Digital de Esterilización')
@section('subtitulo', 'Validación en tiempo real de instrumental estéril')

@section('acciones')
    <a href="{{ route('admin.esterilizacion.index') }}" class="btn btn-outline-secondary">
        <i class="ti ti-arrow-left me-1"></i>Listado de Bioseguridad
    </a>
    <a href="{{ route('admin.esterilizacion.etiquetas', $ciclo) }}" target="_blank" class="btn btn-primary">
        <i class="ti ti-printer me-1"></i>Imprimir Etiquetas
    </a>
@endsection

@section('contenido')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        @php
            $estaVencido = now()->startOfDay()->gt($ciclo->fecha_caducidad_paquetes);
            $esValido = ($ciclo->resultado === 'APROBADO') && ! $estaVencido;
        @endphp

        <div class="card text-center mb-3">
            <div class="card-status-top {{ $esValido ? 'bg-success' : 'bg-danger' }}"></div>
            <div class="card-body py-5">
                <div class="mb-3">
                    @if ($esValido)
                        <span class="avatar avatar-xl bg-success-lt rounded-circle">
                            <i class="ti ti-shield-check text-success" style="font-size: 3rem;"></i>
                        </span>
                    @elseif ($estaVencido)
                        <span class="avatar avatar-xl bg-warning-lt rounded-circle">
                            <i class="ti ti-clock-pause text-warning" style="font-size: 3rem;"></i>
                        </span>
                    @else
                        <span class="avatar avatar-xl bg-danger-lt rounded-circle">
                            <i class="ti ti-shield-x text-danger" style="font-size: 3rem;"></i>
                        </span>
                    @endif
                </div>

                <h1 class="card-title fs-1 mb-2">
                    @if ($esValido)
                        <span class="text-success">INSTRUMENTAL ESTÉRIL Y CONFORME</span>
                    @elseif ($estaVencido)
                        <span class="text-warning">MATERIAL CADUCADO - RE-ESTERILIZAR</span>
                    @else
                        <span class="text-danger">RECHAZADO - NO APTO PARA USO CLÍNICO</span>
                    @endif
                </h1>

                <p class="text-secondary fs-3">
                    Autoclave: <strong>{{ $ciclo->autoclave_nombre }}</strong> &middot; Ciclo <strong>#{{ $ciclo->numero_ciclo }}</strong>
                </p>

                <div class="hr-text">Detalles de Trazabilidad</div>

                <div class="row g-3 text-start mt-2">
                    <div class="col-sm-6">
                        <div class="card card-sm bg-light">
                            <div class="card-body">
                                <div class="text-secondary small">Fecha de Procesamiento</div>
                                <div class="fw-bold fs-3">{{ $ciclo->fecha->format('d/m/Y') }} {{ $ciclo->hora_inicio }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card card-sm bg-light">
                            <div class="card-body">
                                <div class="text-secondary small">Fecha Límite de Esterilidad</div>
                                <div class="fw-bold fs-3 {{ $estaVencido ? 'text-danger' : 'text-success' }}">{{ $ciclo->fecha_caducidad_paquetes->format('d/m/Y') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card card-sm bg-light">
                            <div class="card-body">
                                <div class="text-secondary small">Temperatura y Presión Registradas</div>
                                <div class="fw-bold">{{ $ciclo->temperatura }} °C &middot; {{ $ciclo->presion }} bar ({{ $ciclo->tiempo_esterilizacion }} min)</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card card-sm bg-light">
                            <div class="card-body">
                                <div class="text-secondary small">Controles Químico y Biológico</div>
                                <div class="fw-bold">Químico: {{ $ciclo->indicador_quimico }} &middot; Bio: {{ $ciclo->indicador_biologico }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card card-sm bg-light">
                            <div class="card-body">
                                <div class="text-secondary small">Operador Responsable / Sede</div>
                                <div class="fw-medium">{{ $ciclo->usuario?->name ?? 'Responsable de Esterilización' }} &middot; {{ $ciclo->sucursal?->nombre ?? 'Clínica Principal' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 text-muted small">
                    Token Criptográfico de Seguridad: <code>{{ $ciclo->qr_token }}</code>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
