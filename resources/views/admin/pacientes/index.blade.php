@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', 'Pacientes')

@section('acciones')
    @can('pacientes.crear')
        <a href="{{ route('admin.pacientes.create') }}" class="btn btn-primary">
            <i class="ti ti-user-plus me-1"></i>Nuevo Paciente
        </a>
    @endcan
@endsection

@section('contenido')
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Pacientes registrados" :valor="$totales['registrados']" icono="ti ti-users" color="primary" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Activos" :valor="$totales['activos']" icono="ti ti-user-check" color="success" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Nuevos este mes" :valor="$totales['nuevosMes']" icono="ti ti-user-plus" color="azure" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Con saldo pendiente" :valor="$totales['conSaldo']" icono="ti ti-alert-circle" color="warning" />
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Padrón de Pacientes</h3></div>
    <div class="card-body border-bottom py-3">
        <form method="GET" class="row g-2">
            <div class="col-md">
                <input type="search" name="buscar" value="{{ request('buscar') }}" class="form-control"
                       placeholder="Nombre, apellido, documento, teléfono o correo">
            </div>
            <div class="col-md-3">
                <select name="estado" class="form-select">
                    <option value="">— Todos —</option>
                    <option value="activo" @selected(request('estado') === 'activo')>Activos</option>
                    <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivos</option>
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
                    <th>Paciente</th>
                    <th>Documento</th>
                    <th>Contacto</th>
                    <th class="text-center">Edad</th>
                    <th class="text-center">Citas</th>
                    <th class="text-center">Estado</th>
                    <th class="w-1">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pacientes as $paciente)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                @if ($paciente->fotografia)
                                    <span class="avatar avatar-sm" style="background-image: url({{ Storage::url($paciente->fotografia) }})"></span>
                                @else
                                    <span class="avatar avatar-sm bg-blue-lt">
                                        {{ mb_substr($paciente->nombres, 0, 1) }}{{ mb_substr($paciente->apellidos, 0, 1) }}
                                    </span>
                                @endif
                                <div>
                                    <div class="fw-medium">{{ $paciente->nombre_completo }}</div>
                                    <div class="text-secondary small">
                                        {{ ['M' => 'Masculino', 'F' => 'Femenino', 'O' => 'Otro'][$paciente->genero] ?? '' }}
                                        @if ($paciente->grupo_sanguineo) · {{ $paciente->grupo_sanguineo }} @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="text-secondary">{{ $paciente->tipo_documento }} {{ $paciente->numero_documento }}</td>
                        <td class="text-secondary small">
                            @if ($paciente->telefono)<div><i class="ti ti-phone me-1"></i>{{ $paciente->telefono }}</div>@endif
                            @if ($paciente->email)<div><i class="ti ti-mail me-1"></i>{{ $paciente->email }}</div>@endif
                            @if (! $paciente->telefono && ! $paciente->email)—@endif
                        </td>
                        <td class="text-center">{{ $paciente->edad !== null ? $paciente->edad.' años' : '—' }}</td>
                        <td class="text-center">{{ $paciente->citas_count }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $paciente->activo ? 'success' : 'secondary' }}-lt">
                                {{ $paciente->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td>
                            <div class="btn-list flex-nowrap">
                                <a href="{{ route('admin.pacientes.show', $paciente) }}" class="btn btn-sm btn-outline-secondary" title="Ver ficha">
                                    <i class="ti ti-eye"></i>
                                </a>
                                @can('historiales.ver')
                                    <a href="{{ route('admin.historiales.index', $paciente) }}" class="btn btn-sm btn-outline-azure" title="Historia clínica">
                                        <i class="ti ti-notes-medical"></i>
                                    </a>
                                @endcan
                                @can('odontogramas.ver')
                                    <a href="{{ route('admin.odontogramas.index', $paciente) }}" class="btn btn-sm btn-outline-purple" title="Odontograma">
                                        <i class="ti ti-dental"></i>
                                    </a>
                                @endcan
                                @can('pacientes.editar')
                                    <a href="{{ route('admin.pacientes.edit', $paciente) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <x-vacio icono="ti ti-user-off" titulo="Sin pacientes"
                                     texto="Ningún paciente coincide con la búsqueda." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($pacientes->hasPages())
        <div class="card-footer">{{ $pacientes->links() }}</div>
    @endif
</div>
@endsection
