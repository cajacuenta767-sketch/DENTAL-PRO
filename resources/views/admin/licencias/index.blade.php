@extends('layouts.admin')

@section('pretitulo', 'Proveedor')
@section('titulo', 'Licencias emitidas')

@section('contenido')
@if (session('codigo_emitido'))
    @php $emitido = session('codigo_emitido'); @endphp
    <div class="card border-success mb-3">
        <div class="card-body">
            <div class="d-flex align-items-center mb-2">
                <h3 class="card-title mb-0">
                    <i class="ti ti-key text-success me-1"></i>
                    Código de {{ $emitido['tipo'] }} para {{ $emitido['cliente'] }}
                </h3>
                <button type="button" class="btn btn-sm btn-success ms-auto" data-os-copiar="#codigo-emitido">
                    <i class="ti ti-copy me-1"></i>Copiar
                </button>
            </div>
            <textarea id="codigo-emitido" class="form-control font-monospace" rows="{{ $emitido['tipo'] === 'activación' ? 3 : 1 }}" readonly>{{ $emitido['codigo'] }}</textarea>
            <div class="form-hint mt-2">
                Envíaselo al cliente por WhatsApp. También queda guardado en la lista por si lo necesitas de nuevo.
            </div>
        </div>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Nuevo cliente</h3></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.licencias.store') }}">
                    @csrf
                    <x-campo nombre="cliente" etiqueta="Clínica o cliente" requerido>
                        <input type="text" id="cliente" name="cliente" class="form-control" value="{{ old('cliente') }}" required>
                    </x-campo>
                    <x-campo nombre="contacto" etiqueta="Contacto" ayuda="WhatsApp o correo, para ubicarlo después.">
                        <input type="text" id="contacto" name="contacto" class="form-control" value="{{ old('contacto') }}">
                    </x-campo>
                    <x-campo nombre="tipo" etiqueta="Tipo" requerido>
                        <select id="tipo" name="tipo" class="form-select">
                            <option value="PRUEBA" @selected(old('tipo', 'PRUEBA') === 'PRUEBA')>Prueba gratuita</option>
                            <option value="COMPLETA" @selected(old('tipo') === 'COMPLETA')>Licencia completa</option>
                        </select>
                    </x-campo>
                    @include('admin.licencias._duracion', ['dias' => 7])
                    <x-campo nombre="notas" etiqueta="Notas">
                        <input type="text" id="notas" name="notas" class="form-control" value="{{ old('notas') }}">
                    </x-campo>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ti ti-key me-1"></i>Emitir código de activación
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Clientes</h3>
                <span class="badge bg-secondary ms-2">{{ $emitidas->count() }}</span>
            </div>
            @if ($emitidas->isEmpty())
                <div class="card-body">
                    <x-vacio icono="ti ti-key" titulo="Todavía no emitiste licencias"
                             texto="Registra un cliente en el formulario y recibirás su código de activación." />
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Tipo</th>
                                <th>Vence</th>
                                <th>Instalación</th>
                                <th class="w-1"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($emitidas as $emitida)
                                <tr>
                                    <td>
                                        <div class="fw-medium">{{ $emitida->cliente }}</div>
                                        <div class="text-secondary small">{{ $emitida->contacto ?? 'Sin contacto' }} · #{{ $emitida->id }}</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $emitida->tipo === 'PRUEBA' ? 'azure' : 'green' }}-lt">
                                            {{ $emitida->es_vitalicia ? 'VITALICIA' : $emitida->tipo }}
                                        </span>
                                    </td>
                                    <td>{{ $emitida->es_vitalicia ? 'Nunca' : $emitida->vence_en->format('d/m/Y') }}</td>
                                    <td class="font-monospace">{{ $emitida->codigo_instalacion ?? '—' }}</td>
                                    <td>
                                        <div class="btn-list flex-nowrap">
                                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                                    data-bs-target="#renovar-{{ $emitida->id }}">
                                                Renovar
                                            </button>
                                            <div class="dropdown">
                                                <button class="btn btn-sm dropdown-toggle" data-bs-toggle="dropdown">Más</button>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    @if ($emitida->ultimo_codigo)
                                                        <button type="button" class="dropdown-item" data-os-copiar="#ultimo-{{ $emitida->id }}">
                                                            <i class="ti ti-copy me-2"></i>Copiar último código
                                                        </button>
                                                        <textarea id="ultimo-{{ $emitida->id }}" class="d-none">{{ $emitida->ultimo_codigo }}</textarea>
                                                    @endif
                                                    <button type="button" class="dropdown-item" data-bs-toggle="modal"
                                                            data-bs-target="#reactivar-{{ $emitida->id }}">
                                                        <i class="ti ti-refresh me-2"></i>Nuevo código de activación
                                                    </button>
                                                    <form method="POST" action="{{ route('admin.licencias.destroy', $emitida) }}"
                                                          onsubmit="return confirm('¿Eliminar a {{ $emitida->cliente }}? No podrás renovarle más.');">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="ti ti-trash me-2"></i>Eliminar
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

@foreach ($emitidas as $emitida)
    <div class="modal fade" id="renovar-{{ $emitida->id }}" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('admin.licencias.renovar', $emitida) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Renovar a {{ $emitida->cliente }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <x-campo nombre="codigo_instalacion" etiqueta="Código de instalación del cliente" requerido
                             ayuda="Aparece en su pantalla Licencia, con el formato XXXX-XXXX.">
                        <input type="text" name="codigo_instalacion" class="form-control font-monospace text-uppercase"
                               value="{{ $emitida->codigo_instalacion }}" placeholder="7K3M-9P2Q" required>
                    </x-campo>
                    @include('admin.licencias._duracion', ['dias' => 365])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Generar PIN</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="reactivar-{{ $emitida->id }}" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('admin.licencias.reactivar', $emitida) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo código de activación para {{ $emitida->cliente }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary">Úsalo si el cliente reinstaló el sistema. Conserva su misma cadena de renovación.</p>
                    @include('admin.licencias._duracion', ['dias' => 365])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Generar código</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
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
