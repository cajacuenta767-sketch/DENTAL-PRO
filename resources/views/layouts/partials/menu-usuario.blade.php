<div class="dropdown-header d-flex align-items-center gap-2 py-2">
    @if (auth()->user()->avatar)
        <span class="avatar avatar-sm" style="background-image: url({{ auth()->user()->avatar }})"></span>
    @else
        <span class="avatar avatar-sm bg-brand">{{ auth()->user()->iniciales }}</span>
    @endif
    <div class="lh-sm">
        <div class="text-body fw-medium text-truncate">{{ auth()->user()->nombre }}</div>
        <div class="small text-secondary text-truncate">{{ auth()->user()->rol_principal }}</div>
    </div>
</div>
<div class="dropdown-divider"></div>
<a href="{{ route('perfil.edit') }}" class="dropdown-item">
    <i class="ti ti-user me-2"></i>Mi perfil
</a>
@can('ajustes.ver')
    <a href="{{ route('admin.ajustes.edit') }}" class="dropdown-item">
        <i class="ti ti-settings me-2"></i>Ajustes de la clínica
    </a>
@endcan
@can('ajustes.reservas')
    <a href="{{ route('admin.reservas.edit') }}" class="dropdown-item">
        <i class="ti ti-qrcode me-2"></i>Turnos online
    </a>
@endcan
<a href="{{ route('publico.inicio') }}" class="dropdown-item" target="_blank" rel="noopener">
    <i class="ti ti-world me-2"></i>Sitio público
</a>
<div class="dropdown-divider"></div>
<form method="POST" action="{{ route('logout') }}">
    @csrf
    <button type="submit" class="dropdown-item text-danger">
        <i class="ti ti-logout me-2"></i>Cerrar sesión
    </button>
</form>
