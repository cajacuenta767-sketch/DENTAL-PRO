{{-- Bloqueo de Sillón Clínico (Chairside Privacy Lock: Ctrl+L) --}}
<div id="os-bloqueo-sillon-overlay" class="d-none" style="position: fixed; inset: 0; z-index: 99999; background: rgba(9, 14, 23, 0.95); backdrop-filter: blur(15px); display: flex; align-items: center; justify-content: center; user-select: none;">
    <div class="card shadow-lg border-0" style="max-width: 440px; width: 90%; background: #131b2a; color: #f8fafc; border-radius: 20px;">
        <div class="card-body p-4 p-md-5 text-center">
            <div class="mb-4">
                <span class="avatar avatar-xl bg-teal-lt rounded-circle shadow">
                    <i class="ti ti-lock text-teal" style="font-size: 2.5rem;"></i>
                </span>
            </div>
            
            <h2 class="card-title text-white fs-2 mb-1">Sillón Clínico Bloqueado</h2>
            <p class="text-secondary small mb-4">
                La pantalla ha sido protegida por confidencialidad clínica (HIPAA / GDPR).
            </p>

            <div class="d-flex align-items-center justify-content-center gap-3 p-3 rounded bg-dark-lt mb-4" style="background: rgba(255,255,255,0.04);">
                <span class="avatar bg-primary text-white rounded-circle">
                    {{ substr(auth()->user()?->name ?? 'U', 0, 1) }}
                </span>
                <div class="text-start">
                    <div class="fw-bold text-white">{{ auth()->user()?->name ?? 'Doctor / Asistente' }}</div>
                    <div class="small text-secondary">{{ auth()->user()?->email }}</div>
                </div>
            </div>

            <div id="os-bloqueo-error" class="alert alert-danger py-2 small d-none mb-3" role="alert"></div>

            <form id="os-bloqueo-form" onsubmit="desbloquearSillonClinico(event)">
                <div class="mb-3 text-start">
                    <label class="form-label text-secondary small required">Contraseña de usuario</label>
                    <div class="input-group input-group-flat">
                        <input type="password" id="os-bloqueo-password" class="form-control form-control-lg bg-dark text-white border-secondary" placeholder="Ingresa tu contraseña" required autofocus autocomplete="current-password">
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" id="os-bloqueo-btn" class="btn btn-teal btn-lg">
                        <i class="ti ti-lock-open me-2"></i>Desbloquear Sillón
                    </button>
                </div>
            </form>

            <div class="mt-4 pt-3 border-top border-secondary-subtle d-flex justify-content-between align-items-center">
                <span class="text-muted small"><kbd>Ctrl+L</kbd> para alternar</span>
                <form action="{{ route('logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-link btn-sm text-danger p-0">Cerrar sesión completa</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function activarBloqueoSillon() {
        const overlay = document.getElementById('os-bloqueo-sillon-overlay');
        if (!overlay) return;
        overlay.classList.remove('d-none');
        sessionStorage.setItem('os-sillon-bloqueado', '1');
        const passInput = document.getElementById('os-bloqueo-password');
        if (passInput) {
            passInput.value = '';
            setTimeout(() => passInput.focus(), 150);
        }
        document.getElementById('os-bloqueo-error').classList.add('d-none');
    }

    async function desbloquearSillonClinico(e) {
        e.preventDefault();
        const pass = document.getElementById('os-bloqueo-password').value;
        const errDiv = document.getElementById('os-bloqueo-error');
        const btn = document.getElementById('os-bloqueo-btn');

        if (!pass) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verificando...';
        errDiv.classList.add('d-none');

        try {
            const res = await fetch("{{ route('perfil.desbloquear-sillon') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({ password: pass })
            });

            const data = await res.json();

            if (res.ok && data.ok) {
                sessionStorage.removeItem('os-sillon-bloqueado');
                document.getElementById('os-bloqueo-sillon-overlay').classList.add('d-none');
                document.getElementById('os-bloqueo-password').value = '';
                if (window.toastExito) {
                    window.toastExito("Sillón clínico desbloqueado.");
                }
            } else {
                errDiv.textContent = data.mensaje || 'Contraseña incorrecta.';
                errDiv.classList.remove('d-none');
                document.getElementById('os-bloqueo-password').select();
            }
        } catch (err) {
            errDiv.textContent = 'Error de conexión con el servidor.';
            errDiv.classList.remove('d-none');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="ti ti-lock-open me-2"></i>Desbloquear Sillón';
        }
    }

    // Atajo de teclado: Ctrl+L o Cmd+L
    window.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'l' || e.key === 'L')) {
            e.preventDefault();
            activarBloqueoSillon();
        }
    });

    // Restaurar bloqueo si la sesión local estaba bloqueada
    document.addEventListener('DOMContentLoaded', function() {
        if (sessionStorage.getItem('os-sillon-bloqueado') === '1') {
            activarBloqueoSillon();
        }
    });
</script>
