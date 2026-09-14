@extends('layouts.portal')

@section('pretitulo', 'Portal del paciente')
@section('titulo', 'Firmar consentimiento')

@push('head')
<style>
    .os-consentimiento { white-space: pre-wrap; line-height: 1.7; font-size: 1rem; }
    #firma-canvas { touch-action: none; background: #fff; cursor: crosshair; }
</style>
@endpush

@section('acciones')
    <a href="{{ route('portal.documentos') }}" class="btn btn-outline-secondary">
        <i class="ti ti-arrow-left me-1"></i>Mis documentos
    </a>
@endsection

@section('contenido')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title mb-0"><i class="{{ $documento->icono }} me-2 text-primary"></i>{{ $documento->titulo }}</h3>
                    <div class="text-secondary small">
                        Folio <code>{{ $documento->folio }}</code> · emitido el {{ $documento->fecha_emision?->format('d/m/Y') }}
                    </div>
                </div>
                <div class="card-actions">
                    <a href="{{ route('portal.documentos.pdf', $documento) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                        <i class="ti ti-file-type-pdf me-1"></i>Ver PDF
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="datagrid mb-4">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Paciente</div>
                        <div class="datagrid-content">{{ $paciente->nombres }} {{ $paciente->apellidos }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Doctor</div>
                        <div class="datagrid-content">
                            {{ $documento->doctor?->nombre_profesional ?? '—' }}
                            @if ($documento->doctor?->especialidad)
                                <div class="text-secondary small">{{ $documento->doctor->especialidad->nombre }}</div>
                            @endif
                        </div>
                    </div>
                    @if ($documento->cita?->tratamiento)
                        <div class="datagrid-item">
                            <div class="datagrid-title">Procedimiento</div>
                            <div class="datagrid-content">{{ $documento->cita->tratamiento->nombre }}</div>
                        </div>
                    @endif
                </div>

                <div class="os-consentimiento">{{ $documento->contenido }}</div>

                @if ($documento->indicaciones)
                    <div class="alert alert-info mt-4 mb-0">
                        <div class="fw-bold mb-1"><i class="ti ti-notes me-1"></i>Indicaciones</div>
                        <div class="os-consentimiento">{{ $documento->indicaciones }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <form method="POST" action="{{ route('portal.documentos.firmar.guardar', $documento) }}" id="form-firma">
            @csrf
            <div class="card sticky-top" style="top: 5rem;" id="tarjeta-firma">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-writing-sign me-2"></i>Tu firma</h3>
                </div>
                <div class="card-body">
                    <p class="text-secondary small mb-2">
                        Firma con el dedo, el lápiz o el ratón dentro del recuadro. La firma se imprime en el PDF del consentimiento.
                    </p>
                    <canvas id="firma-canvas" width="600" height="200" class="border rounded w-100"></canvas>
                    <input type="hidden" name="firma" id="firma-input">
                    @error('firma')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="firma-limpiar">
                            <i class="ti ti-eraser me-1"></i>Limpiar
                        </button>
                    </div>

                    <label class="form-check mt-3 mb-0">
                        <input type="checkbox" name="acepto" value="1" class="form-check-input" @checked(old('acepto')) required>
                        <span class="form-check-label">
                            Declaro que leí el consentimiento, comprendo el procedimiento, sus riesgos y alternativas, y lo acepto.
                        </span>
                    </label>
                    @error('acepto')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a href="{{ route('portal.documentos') }}" class="btn">Cancelar</a>
                    <button type="submit" class="btn btn-primary" id="firma-enviar">
                        <i class="ti ti-signature me-1"></i>Firmar consentimiento
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(() => {
    // Pad de firma: dibuja con ratón, lápiz o dedo mediante pointer events (mismo pad que el panel).
    const canvas = document.getElementById('firma-canvas');
    const entrada = document.getElementById('firma-input');
    const limpiar = document.getElementById('firma-limpiar');
    const formulario = canvas?.closest('form');
    if (!canvas || !entrada || !formulario) return;

    const ctx = canvas.getContext('2d');
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#1a1a1a';

    let dibujando = false;
    let hayTrazos = false;

    function punto(e) {
        const r = canvas.getBoundingClientRect();
        return {
            x: (e.clientX - r.left) * (canvas.width / r.width),
            y: (e.clientY - r.top) * (canvas.height / r.height),
        };
    }

    canvas.addEventListener('pointerdown', (e) => {
        e.preventDefault();
        canvas.setPointerCapture(e.pointerId);
        dibujando = true;
        const p = punto(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
    });

    canvas.addEventListener('pointermove', (e) => {
        if (!dibujando) return;
        e.preventDefault();
        const p = punto(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        hayTrazos = true;
    });

    const terminar = (e) => {
        if (!dibujando) return;
        dibujando = false;
        if (e.pointerId !== undefined && canvas.hasPointerCapture(e.pointerId)) {
            canvas.releasePointerCapture(e.pointerId);
        }
        ctx.closePath();
    };
    canvas.addEventListener('pointerup', terminar);
    canvas.addEventListener('pointercancel', terminar);
    canvas.addEventListener('pointerleave', terminar);

    limpiar?.addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hayTrazos = false;
        entrada.value = '';
    });

    formulario.addEventListener('submit', (e) => {
        if (!hayTrazos) {
            e.preventDefault();
            canvas.classList.add('border-danger');
            canvas.focus?.();
            return;
        }
        canvas.classList.remove('border-danger');
        entrada.value = canvas.toDataURL('image/png');
    });
})();
</script>
@endpush
@endsection
