@extends('layouts.admin')
@section('titulo', 'Clínicas y activaciones')
@section('contenido')
<div class="page-header"><div class="container-xl"><h2 class="page-title">Clínicas y activaciones</h2><div class="text-secondary mt-1">Crea accesos de comprador de un solo uso y controla las clínicas de la plataforma.</div></div></div>
<div class="page-body"><div class="container-xl">
    @include('componentes.alertas')
    @if(session('codigo_generado'))
        <div class="alert alert-success"><div><strong>Código completo (se mostrará solo ahora):</strong><div class="font-monospace fs-2 mt-2 user-select-all">{{ session('codigo_generado') }}</div><small>Cópialo y entrégalo de forma segura al comprador.</small></div></div>
    @endif
    <div class="row row-cards">
        <div class="col-lg-4"><div class="card"><div class="card-header"><h3 class="card-title">Nueva activación</h3></div><div class="card-body">
            <form method="POST" action="{{ route('admin.activaciones.store') }}">@csrf
                <div class="mb-3"><label class="form-label">Correo del comprador</label><input class="form-control" type="email" name="email" value="{{ old('email') }}" placeholder="Opcional, pero recomendado">@error('email')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                <div class="mb-3"><label class="form-label required">Plan</label><select class="form-select" name="plan"><option>PRUEBA</option><option>BÁSICO</option><option>PROFESIONAL</option><option>EMPRESA</option></select></div>
                <div class="mb-3"><label class="form-label required">Vigencia (días)</label><input class="form-control" type="number" name="dias" value="{{ old('dias', 30) }}" min="1" max="3650"></div>
                <button class="btn btn-primary w-100"><i class="ti ti-key me-2"></i>Generar activación</button>
            </form>
        </div></div></div>
        <div class="col-lg-8"><div class="card"><div class="card-header"><h3 class="card-title">Activaciones emitidas</h3></div><div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Código</th><th>Comprador</th><th>Plan</th><th>Estado</th><th></th></tr></thead><tbody>
            @forelse($activaciones as $item)<tr><td class="font-monospace">@if($item->codigo_cifrado)<details><summary class="text-primary cursor-pointer">{{ $item->codigo_visible }} · Ver</summary><div class="mt-2 d-flex gap-2 align-items-center"><code class="user-select-all" id="codigo-{{ $item->id }}">{{ $item->codigo_cifrado }}</code><button type="button" class="btn btn-sm btn-ghost-primary" onclick="navigator.clipboard.writeText(document.getElementById('codigo-{{ $item->id }}').textContent)"><i class="ti ti-copy"></i></button></div></details>@else{{ $item->codigo_visible }}<div class="small text-secondary">Código anterior no recuperable</div>@endif</td><td>{{ $item->email ?: 'Sin correo restringido' }}@if($item->usuario)<div class="small text-secondary">Usado por {{ $item->usuario->nombre }}</div>@endif</td><td>{{ $item->datos['plan'] ?? 'PRUEBA' }}</td><td>@if(!$item->activa)<span class="badge bg-secondary-lt">Usada/revocada</span>@elseif($item->vence_en?->isPast())<span class="badge bg-danger-lt">Vencida</span>@else<span class="badge bg-success-lt">Disponible</span>@endif</td><td>@if($item->activa)<form method="POST" action="{{ route('admin.activaciones.revocar', $item) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-danger">Revocar</button></form>@endif</td></tr>@empty<tr><td colspan="5" class="text-center text-secondary py-4">Todavía no emitiste activaciones.</td></tr>@endforelse
        </tbody></table></div><div class="card-footer">{{ $activaciones->links() }}</div></div></div>
    </div>
    <div class="card mt-4"><div class="card-header"><h3 class="card-title">Clínicas registradas</h3></div><div class="table-responsive"><table class="table"><thead><tr><th>Clínica</th><th>Plan</th><th>Usuarios</th><th>Vencimiento</th><th>Estado</th><th></th></tr></thead><tbody>
        @forelse($clinicas as $clinica)<tr><td>{{ $clinica->nombre }}</td><td>{{ $clinica->plan }}</td><td>{{ $clinica->usuarios_count }}</td><td>{{ $clinica->vence_en?->format('d/m/Y') ?? 'Sin límite' }}</td><td>{{ $clinica->estado }}</td><td><form method="POST" action="{{ route('admin.clinicas.estado', $clinica) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary">{{ $clinica->estado === 'ACTIVA' ? 'Suspender' : 'Activar' }}</button></form></td></tr>@empty<tr><td colspan="6" class="text-center text-secondary py-4">Aún no hay clínicas registradas.</td></tr>@endforelse
    </tbody></table></div></div>
</div></div>
@endsection
