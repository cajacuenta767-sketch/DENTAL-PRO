@extends('layouts.portal')

@section('pretitulo', 'Portal del paciente')
@section('titulo', 'Mis documentos')

@section('contenido')
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-file-text me-2 text-primary"></i>Documentos clínicos emitidos</h3>
        <div class="card-actions text-secondary small">
            {{ $documentos->total() }} {{ Str::plural('documento', $documentos->total()) }}
        </div>
    </div>

    @if ($documentos->isEmpty())
        <div class="card-body">
            <x-vacio icono="ti ti-file-off" titulo="No tienes documentos emitidos"
                     texto="Las recetas, certificados y demás documentos que emita tu doctor aparecerán aquí." />
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Tipo</th>
                        <th>Título</th>
                        <th>Fecha</th>
                        <th>Doctor</th>
                        <th>Vigencia</th>
                        <th>Firma</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($documentos as $documento)
                        <tr>
                            <td class="text-nowrap"><code>{{ $documento->folio }}</code></td>
                            <td class="text-nowrap">
                                <i class="{{ $documento->icono }} me-1 text-secondary"></i>{{ $documento->tipo_legible }}
                            </td>
                            <td>{{ $documento->titulo }}</td>
                            <td class="text-nowrap">{{ $documento->fecha_emision?->format('d/m/Y') }}</td>
                            <td>{{ $documento->doctor?->nombre_profesional ?? '—' }}</td>
                            <td class="text-nowrap">
                                @if ($documento->vence_el)
                                    @if ($documento->esta_vigente)
                                        <span class="text-secondary">Hasta {{ $documento->vence_el->format('d/m/Y') }}</span>
                                    @else
                                        <span class="badge bg-secondary-lt">Vencido {{ $documento->vence_el->format('d/m/Y') }}</span>
                                    @endif
                                @else
                                    <span class="text-secondary">Sin vencimiento</span>
                                @endif
                            </td>
                            <td>
                                @if ($documento->esta_firmado)
                                    <span class="badge bg-success-lt"><i class="ti ti-signature me-1"></i>Firmado</span>
                                @else
                                    <span class="badge bg-secondary-lt">Sin firma</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('portal.documentos.pdf', $documento) }}" target="_blank" rel="noopener"
                                   class="btn btn-sm btn-outline-primary text-nowrap">
                                    <i class="ti ti-file-type-pdf me-1"></i>Ver PDF
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($documentos->hasPages())
            <div class="card-footer d-flex align-items-center">
                {{ $documentos->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
