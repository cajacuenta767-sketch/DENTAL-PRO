@extends('layouts.admin')

@section('pretitulo', 'Configuración')
@section('titulo', 'Copias de seguridad')

@section('acciones')
    @can('respaldos.crear')
        <form method="POST" action="{{ route('admin.respaldos.crear') }}"
              data-confirmar="Se generará una copia completa de la base de datos y los archivos privados. Puede tardar unos minutos. ¿Continuar?">
            @csrf
            <button type="submit" class="btn btn-primary" @disabled(! $herramientasDisponibles)>
                <i class="ti ti-database-export me-1"></i>Crear ahora
            </button>
        </form>
    @endcan
@endsection

@section('contenido')
@unless ($herramientasDisponibles)
    <div class="alert alert-warning" role="alert">
        <div class="d-flex">
            <div><i class="ti ti-alert-triangle fs-2 me-2"></i></div>
            <div>
                <h4 class="alert-title">pg_dump / pg_restore no están disponibles en el servidor</h4>
                <div class="text-secondary">
                    Instala el cliente de PostgreSQL (<code>postgresql-client</code> o <code>postgresql16-client</code> en Alpine)
                    o indica su carpeta en la variable <code>RESPALDOS_RUTA_PG</code> del archivo <code>.env</code>.
                </div>
            </div>
        </div>
    </div>
@endunless

<div class="row row-cards mb-3">
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Respaldos guardados" :valor="$respaldos->count()" icono="ti ti-archive" color="primary"
               pie="se conservan los {{ $conservar }} más recientes" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Espacio utilizado" :valor="Number::fileSize($tamanoTotal, precision: 1)" icono="ti ti-server" color="azure" pie="en storage/app/respaldos" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Último respaldo" :valor="$ultimo ? $ultimo['fecha']->format('d/m/Y H:i') : '—'" icono="ti ti-clock-check"
               :color="$ultimo ? 'success' : 'secondary'" :pie="$ultimo ? $ultimo['fecha']->diffForHumans() : 'aún no hay copias'" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Programación" valor="02:00" icono="ti ti-calendar-time" color="teal" pie="copia automática diaria" />
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-database me-2"></i>Respaldos disponibles</h3>
        <div class="card-actions text-secondary small">
            Base de datos + estudios, fotografías y firmas
        </div>
    </div>

    @if ($respaldos->isEmpty())
        <div class="card-body">
            <x-vacio icono="ti ti-database-off" titulo="Aún no hay copias de seguridad"
                     texto="Pulsa «Crear ahora» o espera a la copia automática de las 02:00. También puedes ejecutar php artisan sistema:respaldar." />
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Fecha</th>
                        <th class="text-end">Tamaño</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($respaldos as $respaldo)
                        <tr>
                            <td>
                                <i class="ti ti-file-zip me-1 text-secondary"></i>
                                <span class="font-monospace">{{ $respaldo['nombre'] }}</span>
                                @if ($loop->first)
                                    <span class="badge bg-green-lt ms-2">Más reciente</span>
                                @endif
                            </td>
                            <td>
                                {{ $respaldo['fecha']->format('d/m/Y H:i') }}
                                <div class="small text-secondary">{{ $respaldo['fecha']->diffForHumans() }}</div>
                            </td>
                            <td class="text-end">{{ Number::fileSize($respaldo['tamano'], precision: 1) }}</td>
                            <td class="text-nowrap">
                                <div class="btn-list flex-nowrap justify-content-end">
                                    @can('respaldos.descargar')
                                        <a href="{{ route('admin.respaldos.descargar', $respaldo['nombre']) }}" class="btn btn-sm btn-outline-primary"
                                           aria-label="Descargar {{ $respaldo['nombre'] }}">
                                            <i class="ti ti-download me-1"></i>Descargar
                                        </a>
                                    @endcan
                                    @can('respaldos.crear')
                                        <form method="POST" action="{{ route('admin.respaldos.eliminar', $respaldo['nombre']) }}"
                                              data-confirmar="¿Eliminar el respaldo {{ $respaldo['nombre'] }}? Esta acción no se puede deshacer.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-icon btn-outline-danger"
                                                    aria-label="Eliminar {{ $respaldo['nombre'] }}" title="Eliminar">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="card-footer text-secondary small">
        <i class="ti ti-info-circle me-1"></i>
        Para restaurar una copia ejecuta en el servidor
        <code>php artisan sistema:restaurar respaldo-AAAAMMDD-HHMMSS.zip</code>.
        La restauración reemplaza todos los datos actuales, por eso sólo está disponible desde la consola.
    </div>
</div>
@endsection
