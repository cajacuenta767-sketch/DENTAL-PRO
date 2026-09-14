@extends('layouts.admin')

@section('pretitulo', 'Cuenta')
@section('titulo', 'Mi Perfil')

@section('contenido')
<div class="row g-3">
    <div class="col-lg-7">
        <form method="POST" action="{{ route('perfil.update') }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="card">
                <div class="card-header"><h3 class="card-title">Datos personales</h3></div>
                <div class="card-body">
                    <x-campo nombre="nombre" etiqueta="Nombre completo" requerido>
                        <input type="text" id="nombre" name="nombre" class="form-control" value="{{ old('nombre', $usuario->nombre) }}" required>
                    </x-campo>
                    <x-campo nombre="email" etiqueta="Correo electrónico" requerido
                             ayuda="Si lo cambias deberás verificarlo de nuevo.">
                        <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $usuario->email) }}" required>
                    </x-campo>
                    <x-campo nombre="telefono" etiqueta="Teléfono">
                        <input type="text" id="telefono" name="telefono" class="form-control" value="{{ old('telefono', $usuario->telefono) }}">
                    </x-campo>
                    <x-campo nombre="foto" etiqueta="Foto de perfil" ayuda="JPG o PNG, máximo 2 MB.">
                        <input type="file" id="foto" name="foto" class="form-control" accept="image/*">
                    </x-campo>
                </div>
                <div class="card-footer text-end">
                    <button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Guardar perfil</button>
                </div>
            </div>
        </form>

        @can('api.usar')
            <div class="card mt-3" id="tokens-api">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-api me-2 text-primary"></i>Tokens de API</h3>
                    @if ($tokens->isNotEmpty())
                        <div class="card-actions"><span class="badge bg-blue-lt">{{ $tokens->count() }}</span></div>
                    @endif
                </div>
                <div class="card-body">
                    <p class="text-secondary">
                        Los tokens permiten que otros sistemas consulten y agenden en OdontoSuite en tu nombre
                        (<code>Authorization: Bearer …</code>). Heredan los permisos que tienes al momento de crearlos.
                    </p>

                    @if (session('token_plano'))
                        <div class="alert alert-success" role="alert">
                            <div class="d-flex">
                                <div class="me-2"><i class="ti ti-key fs-2"></i></div>
                                <div class="flex-fill">
                                    <h4 class="alert-title">Token «{{ session('token_nombre') }}» creado</h4>
                                    <div class="text-secondary mb-2">
                                        Cópialo y guárdalo en un lugar seguro: <strong>no volverá a mostrarse</strong>.
                                    </div>
                                    <div class="input-group">
                                        <input type="text" id="token-plano" class="form-control font-monospace" value="{{ session('token_plano') }}" readonly onclick="this.select()">
                                        <button type="button" class="btn btn-outline-success" onclick="navigator.clipboard.writeText(document.getElementById('token-plano').value).then(() => { this.innerText = 'Copiado'; })">
                                            <i class="ti ti-copy me-1"></i>Copiar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($tokens->isEmpty())
                        <div class="text-secondary mb-3"><i class="ti ti-info-circle me-1"></i>Aún no tienes tokens de API.</div>
                    @else
                        <div class="table-responsive mb-3">
                            <table class="table table-vcenter card-table">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Creado</th>
                                        <th>Último uso</th>
                                        <th>Expira</th>
                                        <th class="w-1"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tokens as $token)
                                        <tr>
                                            <td>
                                                <strong>{{ $token->name }}</strong>
                                                <div class="text-secondary small">{{ count($token->abilities ?? []) }} permisos</div>
                                            </td>
                                            <td class="text-secondary">{{ $token->created_at?->format('d/m/Y H:i') }}</td>
                                            <td class="text-secondary">{{ $token->last_used_at?->format('d/m/Y H:i') ?? 'Nunca' }}</td>
                                            <td>
                                                @if ($token->expires_at)
                                                    @if ($token->expires_at->isPast())
                                                        <span class="badge bg-danger-lt">Expiró el {{ $token->expires_at->format('d/m/Y') }}</span>
                                                    @else
                                                        <span class="text-secondary">{{ $token->expires_at->format('d/m/Y') }}</span>
                                                    @endif
                                                @else
                                                    <span class="text-secondary">Nunca</span>
                                                @endif
                                            </td>
                                            <td>
                                                <form method="POST" action="{{ route('perfil.tokens.revocar', $token->id) }}"
                                                      onsubmit="return confirm('¿Revocar el token «{{ $token->name }}»? Las integraciones que lo usen dejarán de funcionar.')">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger" title="Revocar">
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('perfil.tokens.crear') }}">
                        @csrf
                        <div class="row g-2">
                            <div class="col-md-6">
                                <x-campo nombre="nombre_token" etiqueta="Nombre del token" requerido
                                         ayuda="Identifica la integración, por ejemplo «Central telefónica».">
                                    <input type="text" id="nombre_token" name="nombre_token" class="form-control" maxlength="60"
                                           value="{{ old('nombre_token') }}" placeholder="Integración X" required>
                                </x-campo>
                            </div>
                            <div class="col-md-6">
                                <x-campo nombre="expira_en" etiqueta="Expira el" ayuda="Opcional. Déjalo vacío para que no expire.">
                                    <input type="date" id="expira_en" name="expira_en" class="form-control"
                                           value="{{ old('expira_en') }}" min="{{ now()->addDay()->toDateString() }}">
                                </x-campo>
                            </div>
                        </div>
                        <div class="text-end">
                            <button class="btn btn-primary"><i class="ti ti-plus me-1"></i>Crear token</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-body text-center">
                @if ($usuario->avatar)
                    <span class="avatar avatar-xl mb-3" style="background-image: url({{ $usuario->avatar }})"></span>
                @else
                    <span class="avatar avatar-xl bg-brand mb-3">{{ $usuario->iniciales }}</span>
                @endif
                <h3 class="mb-1">{{ $usuario->nombre }}</h3>
                <div class="text-secondary">{{ $usuario->email }}</div>
                <div class="mt-2">
                    @foreach ($usuario->roles as $rol)
                        <span class="badge bg-blue-lt">{{ $rol->name }}</span>
                    @endforeach
                </div>
                <div class="text-secondary small mt-3">
                    <i class="ti ti-clock me-1"></i>Último acceso:
                    {{ $usuario->ultimo_acceso_en?->format('d/m/Y H:i') ?? 'Sin registro' }}
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('perfil.2fa') }}" class="mt-3">
            @csrf @method('PUT')
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-shield-lock me-2 text-primary"></i>Doble factor de autenticación</h3>
                    @if ($usuario->dos_factores)
                        <div class="card-actions"><span class="badge bg-success-lt">Activo</span></div>
                    @endif
                </div>
                <div class="card-body">
                    <p class="text-secondary">
                        Al activarlo, en cada inicio de sesión te enviaremos un código de 6 dígitos a tu correo
                        que deberás ingresar para completar el acceso.
                    </p>

                    @if (! $usuario->hasVerifiedEmail())
                        <div class="alert alert-warning mb-3">
                            <div class="d-flex">
                                <div class="me-2"><i class="ti ti-alert-triangle fs-2"></i></div>
                                <div>
                                    Debes <a href="{{ route('verification.notice') }}" class="alert-link">verificar tu correo</a>
                                    antes de activar el doble factor.
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-check form-switch">
                            <input type="hidden" name="dos_factores" value="0">
                            <input type="checkbox" id="dos_factores" name="dos_factores" value="1" class="form-check-input"
                                   @checked(old('dos_factores', $usuario->dos_factores))
                                   @disabled(! $usuario->hasVerifiedEmail())>
                            <span class="form-check-label">Enviar un código a mi correo al iniciar sesión</span>
                        </label>
                        @error('dos_factores')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-0">
                        <label class="form-label required" for="password_actual_2fa">Contraseña actual</label>
                        <input type="password" id="password_actual_2fa" name="password_actual" class="form-control"
                               autocomplete="current-password" required @disabled(! $usuario->hasVerifiedEmail())>
                        @error('password_actual')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <small class="form-hint">Confirma tu identidad para cambiar esta configuración.</small>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button class="btn btn-outline-primary" @disabled(! $usuario->hasVerifiedEmail())>
                        <i class="ti ti-device-floppy me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </form>

        <form method="POST" action="{{ route('perfil.password') }}" class="mt-3">
            @csrf @method('PUT')
            <div class="card">
                <div class="card-header"><h3 class="card-title">Cambiar contraseña</h3></div>
                <div class="card-body">
                    <x-campo nombre="password_actual" etiqueta="Contraseña actual" requerido>
                        <input type="password" id="password_actual" name="password_actual" class="form-control" autocomplete="current-password" required>
                    </x-campo>
                    <x-campo nombre="password" etiqueta="Nueva contraseña" requerido ayuda="Mínimo 8 caracteres.">
                        <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" required>
                    </x-campo>
                    <x-campo nombre="password_confirmation" etiqueta="Confirmar nueva contraseña" requerido>
                        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                    </x-campo>
                </div>
                <div class="card-footer text-end">
                    <button class="btn btn-outline-primary"><i class="ti ti-key me-1"></i>Cambiar contraseña</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
