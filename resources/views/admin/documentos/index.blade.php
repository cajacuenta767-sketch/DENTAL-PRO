@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', 'Recetas y Certificados')

@section('acciones')
    @can('documentos.crear')
        <a href="{{ route('admin.documentos.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i>Nuevo Documento
        </a>
    @endcan
@endsection

@section('contenido')
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Documentos vigentes" :valor="$totales['emitidos']" icono="ti ti-file-check" color="success" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Recetas" :valor="$totales['recetas']" icono="ti ti-prescription" color="primary" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Certificados" :valor="$totales['certificados']" icono="ti ti-certificate" color="azure" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Emitidos este mes" :valor="$totales['esteMes']" icono="ti ti-calendar-plus" color="indigo" />
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Documentos emitidos</h3></div>
    <div class="card-body border-bottom py-3">
        <form method="GET" class="row g-2">
            <div class="col-md">
                <input type="search" name="buscar" value="{{ request('buscar') }}" class="form-control"
                       placeholder="Folio, título, paciente o documento">
            </div>
            <div class="col-md-3">
                <select name="tipo" class="form-select">
                    <option value="">— Todos los tipos —</option>
                    @foreach (\App\Models\DocumentoClinico::TIPOS as $clave => $etiqueta)
                        <option value="{{ $clave }}" @selected(request('tipo') === $clave)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="estado" class="form-select">
                    <option value="">— Todos —</option>
                    <option value="EMITIDO" @selected(request('estado') === 'EMITIDO')>Emitidos</option>
                    <option value="ANULADO" @selected(request('estado') === 'ANULADO')>Anulados</option>
                </select>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-primary w-100"><i class="ti ti-search me-1"></i>Buscar</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Tipo</th>
                    <th>Paciente</th>
                    <th>Doctor</th>
                    <th>Emisión</th>
                    <th class="text-center">Vigencia</th>
                    <th class="w-1">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documentos as $documento)
                    <tr class="{{ $documento->estado === 'ANULADO' ? 'opacity-75' : '' }}">
                        <td class="font-monospace small">{{ $documento->folio }}</td>
                        <td>
                            <span class="badge bg-azure-lt">
                                <i class="{{ $documento->icono }} me-1"></i>{{ $documento->tipo_legible }}
                            </span>
                        </td>
                        <td>
                            <div class="fw-medium">{{ $documento->paciente->nombre_completo }}</div>
                            <div class="text-secondary small">{{ $documento->paciente->numero_documento }}</div>
                        </td>
                        <td class="text-secondary small">{{ $documento->doctor->nombre_profesional }}</td>
                        <td class="text-secondary">{{ $documento->fecha_emision->format('d/m/Y') }}</td>
                        <td class="text-center">
                            @if ($documento->estado === 'ANULADO')
                                <span class="badge bg-danger-lt">Anulado</span>
                            @elseif ($documento->vence_el)
                                <span class="badge bg-{{ $documento->esta_vigente ? 'success' : 'secondary' }}-lt">
                                    {{ $documento->vence_el->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="badge bg-success-lt">Sin vencimiento</span>
                            @endif
                        </td>
                        <td>
                            <div class="btn-list flex-nowrap">
                                <a href="{{ route('admin.documentos.pdf', $documento) }}" target="_blank"
                                   class="btn btn-sm btn-outline-danger" title="Ver PDF">
                                    <i class="ti ti-file-type-pdf"></i>
                                </a>
                                @can('documentos.editar')
                                    @if ($documento->estado !== 'ANULADO')
                                        <a href="{{ route('admin.documentos.edit', $documento) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                            <i class="ti ti-edit"></i>
                                        </a>
                                    @endif
                                @endcan
                                @can('documentos.anular')
                                    @if ($documento->estado !== 'ANULADO')
                                        <form method="POST" action="{{ route('admin.documentos.anular', $documento) }}"
                                              data-confirmar="¿Anular el documento {{ $documento->folio }}?">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-sm btn-outline-warning" title="Anular"><i class="ti ti-ban"></i></button>
                                        </form>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <x-vacio icono="ti ti-file-off" titulo="Sin documentos"
                                     texto="Ningún documento coincide con los filtros aplicados." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($documentos->hasPages())
        <div class="card-footer">{{ $documentos->links() }}</div>
    @endif
</div>
@endsection
