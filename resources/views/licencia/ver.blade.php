@extends('layouts.admin')

@section('pretitulo', 'Sistema')
@section('titulo', 'Licencia')

@section('contenido')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <h3 class="card-title mb-0">Estado de tu acceso</h3>
                    @php
                        $color = match ($licencia->estado) { 'ACTIVA' => 'success', 'VENCIDA' => 'danger', default => 'secondary' };
                    @endphp
                    <span class="badge bg-{{ $color }} ms-auto text-uppercase">{{ $licencia->estado }}</span>
                </div>

                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Tipo</div>
                        <div class="datagrid-content">{{ $licencia->tipo_etiqueta }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Vence</div>
                        <div class="datagrid-content">
                            @if (! $licencia->estaActivada())
                                —
                            @elseif ($licencia->esVitalicia())
                                Nunca
                            @else
                                {{ $licencia->vence_en->format('d/m/Y') }}
                                @if ($licencia->estaVigente())
                                    <span class="text-secondary">({{ $licencia->diasRestantes() }} {{ $licencia->diasRestantes() === 1 ? 'día' : 'días' }})</span>
                                @endif
                            @endif
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Activada</div>
                        <div class="datagrid-content">{{ $licencia->activada_en?->format('d/m/Y') ?? '—' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Última renovación</div>
                        <div class="datagrid-content">{{ $licencia->renovada_en?->format('d/m/Y') ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    {{ $licencia->estaActivada() ? 'Ingresar PIN de renovación' : 'Activar el sistema' }}
                </h3>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('licencia.activar') }}">
                    @csrf
                    <x-campo nombre="codigo" etiqueta="PIN o código de activación" requerido
                             ayuda="Pega el código tal como lo recibiste. No importan mayúsculas, guiones ni espacios.">
                        <textarea id="codigo" name="codigo" rows="3" class="form-control font-monospace"
                                  placeholder="XXXX-XXXX-XXXX-XXXX-XXXX" required autofocus>{{ old('codigo') }}</textarea>
                    </x-campo>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-key me-1"></i>{{ $licencia->estaActivada() ? 'Renovar' : 'Activar' }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-body text-center">
                <div class="text-secondary text-uppercase small mb-2">Tu código de instalación</div>
                <div class="display-6 font-monospace fw-bold" id="codigo-instalacion">{{ $licencia->codigo_instalacion }}</div>
                <button type="button" class="btn btn-sm mt-3" data-os-copiar="#codigo-instalacion">
                    <i class="ti ti-copy me-1"></i>Copiar
                </button>
                <p class="text-secondary small mt-3 mb-0">
                    Envía este código cuando pidas tu PIN de renovación. Cada PIN funciona solo en esta instalación.
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="card-title">¿Necesitas un PIN?</h3>
                <p class="text-secondary">
                    Escribe a <strong>{{ $contactoNombre }}</strong> con tu código de instalación y recibirás tu PIN.
                </p>
                @if ($contactoWhatsapp)
                    <a href="{{ $contactoWhatsapp }}" target="_blank" rel="noopener" class="btn btn-success w-100">
                        <i class="ti ti-brand-whatsapp me-1"></i>Pedir mi PIN por WhatsApp
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-os-copiar]').forEach((boton) => {
        boton.addEventListener('click', async () => {
            const nodo = document.querySelector(boton.dataset.osCopiar);
            const texto = nodo?.value ?? nodo?.textContent ?? '';
            try { await navigator.clipboard.writeText(texto.trim()); } catch (e) { return; }
            const original = boton.innerHTML;
            boton.innerHTML = '<i class="ti ti-check me-1"></i>Copiado';
            setTimeout(() => { boton.innerHTML = original; }, 1500);
        });
    });
</script>
@endpush
