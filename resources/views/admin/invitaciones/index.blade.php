@extends('layouts.admin')
@section('titulo', 'Invitaciones')
@section('contenido')
<div class="page-header"><div class="container-xl"><h2 class="page-title">Invitar personas</h2><div class="text-secondary mt-1">Entrega acceso a tu clínica sin compartir contraseñas.</div></div></div>
<div class="page-body"><div class="container-xl">
    @include('componentes.alertas')
    @if(session('codigo_generado'))<div class="alert alert-success"><div><strong>Código completo (se mostrará solo ahora):</strong><div class="font-monospace fs-2 mt-2 user-select-all">{{ session('codigo_generado') }}</div></div></div>@endif
    <div class="row row-cards"><div class="col-lg-4"><div class="card"><div class="card-header"><h3 class="card-title">Nueva invitación</h3></div><div class="card-body"><form method="POST" action="{{ route('admin.invitaciones.store') }}">@csrf
        <div class="mb-3"><label class="form-label">Correo</label><input type="email" class="form-control" name="email" value="{{ old('email') }}" placeholder="Opcional; si lo indicas, solo ese correo podrá usarla">@error('email')<div class="text-danger small">{{ $message }}</div>@enderror</div>
        <div class="mb-3"><label class="form-label required">Acceso</label><select class="form-select" name="rol"><option value="DOCTOR">Doctor</option><option value="RECEPCION">Recepción</option><option value="PACIENTE">Paciente</option></select></div>
        <div class="mb-3"><label class="form-label required">Vigencia (días)</label><input type="number" class="form-control" name="dias" value="7" min="1" max="90"></div>
        <button class="btn btn-primary w-100"><i class="ti ti-send me-2"></i>Crear y enviar</button>
    </form></div></div></div>
    <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Código</th><th>Correo</th><th>Rol</th><th>Vence</th><th>Estado</th><th></th></tr></thead><tbody>
        @forelse($invitaciones as $item)<tr><td class="font-monospace">@if($item->codigo_cifrado)<details><summary class="text-primary cursor-pointer">{{ $item->codigo_visible }} · Ver</summary><div class="mt-2 d-flex gap-2 align-items-center"><code class="user-select-all" id="invitacion-{{ $item->id }}">{{ $item->codigo_cifrado }}</code><button type="button" class="btn btn-sm btn-ghost-primary" onclick="navigator.clipboard.writeText(document.getElementById('invitacion-{{ $item->id }}').textContent)"><i class="ti ti-copy"></i></button></div></details>@else{{ $item->codigo_visible }}<div class="small text-secondary">Código anterior no recuperable</div>@endif</td><td>{{ $item->email ?: 'Cualquier correo' }}@if($item->usuario)<div class="small text-secondary">{{ $item->usuario->nombre }}</div>@endif</td><td><span class="badge bg-blue-lt">{{ $item->rol }}</span></td><td>{{ $item->vence_en?->format('d/m/Y') }}</td><td>{{ $item->estaDisponible() ? 'Disponible' : 'Cerrada' }}</td><td>@if($item->activa)<form method="POST" action="{{ route('admin.invitaciones.revocar', $item) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-danger">Revocar</button></form>@endif</td></tr>@empty<tr><td colspan="6" class="text-center text-secondary py-4">No hay invitaciones todavía.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer">{{ $invitaciones->links() }}</div></div></div></div>
</div></div>
@endsection
