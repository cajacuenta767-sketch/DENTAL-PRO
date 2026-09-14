@extends('layouts.auth')

@section('titulo', 'Licencia del sistema')

@php
    $fecha = fn ($valor, $vacio = '—') => $valor ? \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y') : $vacio;
    $fechaHora = fn ($valor, $vacio = '—') => $valor ? \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y H:i') : $vacio;

    if (! $resumen['activo']) {
        [$tono, $titulo] = ['secondary', 'Licencia no exigida en este servidor'];
    } elseif ($sinClave) {
        [$tono, $titulo] = ['warning', 'Falta registrar la clave de licencia'];
    } elseif ($resumen['valido'] && $resumen['estado'] === 'mora') {
        [$tono, $titulo] = ['warning', 'Licencia vencida: renueva pronto'];
    } elseif ($resumen['valido']) {
        [$tono, $titulo] = ['success', 'Licencia activa'];
    } else {
        [$tono, $titulo] = ['danger', 'Licencia no válida'];
    }
@endphp

@section('contenido')
    <a href="{{ auth()->check() ? auth()->user()->destinoInicial() : route('publico.inicio') }}" class="text-secondary text-decoration-none small">
        <i class="ti ti-arrow-left me-1"></i>{{ auth()->check() ? 'Volver al sistema' : 'Volver al inicio' }}
    </a>

    <div class="text-center my-4">
        <span class="avatar avatar-lg bg-{{ $tono }} text-white mb-3"><i class="ti ti-license fs-1"></i></span>
        <h2 class="h1 mb-1">{{ $ajustes->nombre ?? 'DENTAL-PRO' }} · Licencia</h2>
        <span class="badge bg-{{ $tono }}-lt fs-5 px-3" data-campo="titulo">{{ $titulo }}</span>
    </div>

    @include('componentes.alertas')

    @if ($resumen['activo'] && ! $resumen['valido'] && $motivo)
        <div class="alert alert-danger" data-campo="aviso">
            <div class="d-flex">
                <div class="me-2"><i class="ti ti-lock fs-2"></i></div>
                <div>{{ $motivo }}</div>
            </div>
        </div>
    @elseif ($resumen['activo'] && $resumen['valido'] && $resumen['estado'] === 'mora')
        <div class="alert alert-warning" data-campo="aviso">
            La licencia está vencida y en periodo de gracia. Renueva con tu asesor para no perder el acceso.
        </div>
    @endif

    @if ($resumen['desactualizada'])
        <div class="alert alert-info" data-campo="desactualizada">
            <i class="ti ti-download me-1"></i>Hay una versión nueva ({{ $resumen['version_actual'] }}). Pide la actualización a tu asesor.
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0 small">
                <dt class="col-5 text-secondary">Clave</dt>
                <dd class="col-7"><code data-campo="clave">{{ $resumen['clave'] ?? '—' }}</code></dd>

                <dt class="col-5 text-secondary">Plan</dt>
                <dd class="col-7" data-campo="plan">{{ collect([$resumen['plan'], $resumen['etiqueta']])->filter()->implode(' · ') ?: '—' }}</dd>

                <dt class="col-5 text-secondary">Vence</dt>
                <dd class="col-7" data-campo="vence_en">{{ $fecha($resumen['vence_en'], $resumen['valido'] ? 'Nunca' : '—') }}</dd>

                <dt class="col-5 text-secondary">Soporte hasta</dt>
                <dd class="col-7" data-campo="soporte_hasta">{{ $fecha($resumen['soporte_hasta']) }}</dd>

                <dt class="col-5 text-secondary">Funciona sin internet hasta</dt>
                <dd class="col-7" data-campo="sin_conexion_hasta">
                    {{ $fechaHora($resumen['sin_conexion_hasta']) }}
                    @if ($resumen['emergencia'])<span class="badge bg-warning-lt ms-1">código de emergencia</span>@endif
                </dd>

                <dt class="col-5 text-secondary">Equipo</dt>
                <dd class="col-7"><code data-campo="huella">{{ $resumen['huella'] }}</code></dd>

                <dt class="col-5 text-secondary">Versión</dt>
                <dd class="col-7" data-campo="version">
                    {{ $resumen['version'] ?? '—' }}
                    @if ($resumen['desactualizada'])<span class="text-warning ms-1">(nueva: {{ $resumen['version_actual'] }})</span>@endif
                </dd>
            </dl>
        </div>
    </div>

    @if ($puedeGestionar)
        <form method="POST" action="{{ route('licencia.reactivar') }}" class="mb-3">
            @csrf
            <button type="submit" class="btn btn-brand w-100" @disabled($sinClave)>
                <i class="ti ti-refresh me-1"></i>Reactivar / verificar ahora
            </button>
        </form>

        <div class="card mb-3">
            <div class="card-body">
                <h3 class="card-title mb-2"><i class="ti ti-key me-1"></i>{{ $sinClave ? 'Ingresar la clave de licencia' : 'Cambiar la clave de licencia' }}</h3>
                <p class="text-secondary small mb-2">
                    {{ $sinClave ? 'Es el primer arranque: registra la clave que te entregó la agencia para activar este equipo.' : 'Al cambiar la clave se descarta la activación actual y se activa de nuevo.' }}
                </p>
                <form method="POST" action="{{ route('licencia.clave') }}" novalidate>
                    @csrf
                    <div class="input-group">
                        <input type="text" name="clave" value="{{ old('clave') }}" class="form-control text-uppercase @error('clave') is-invalid @enderror"
                               placeholder="CTL-XXXX-XXXX-XXXX-XXXX" maxlength="24" autocomplete="off" required>
                        <button type="submit" class="btn btn-primary">{{ $sinClave ? 'Activar' : 'Cambiar' }}</button>
                    </div>
                    @error('clave')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h3 class="card-title mb-2"><i class="ti ti-lifebuoy me-1"></i>Código de emergencia (72 h)</h3>
                <p class="text-secondary small mb-2">Si CONTROL no responde o no hay internet, tu asesor puede emitir un código para este equipo; se aplica sin conexión.</p>
                <form method="POST" action="{{ route('licencia.emergencia') }}" novalidate>
                    @csrf
                    <div class="input-group">
                        <input type="text" name="codigo" class="form-control @error('codigo') is-invalid @enderror"
                               placeholder="Pega aquí el código de emergencia" autocomplete="off" required>
                        <button type="submit" class="btn">Aplicar</button>
                    </div>
                    @error('codigo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </form>
            </div>
        </div>
    @elseif (! auth()->check())
        <a href="{{ route('login') }}" class="btn btn-brand w-100 mb-3">
            <i class="ti ti-login me-1"></i>Iniciar sesión para gestionar la licencia
        </a>
    @else
        <div class="alert alert-secondary">Solo un administrador con permiso de ajustes puede reactivar la licencia o registrar una clave nueva.</div>
    @endif

    <p class="text-secondary small text-center mb-0">
        Si el sistema está bloqueado, escribe a tu asesor con la clave y el identificador del equipo.
    </p>
@endsection
