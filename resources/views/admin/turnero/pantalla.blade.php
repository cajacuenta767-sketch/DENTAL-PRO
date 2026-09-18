<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Turnero TV - {{ $clinica->nombre ?? 'DENTAL PRO' }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <style>
        :root {
            --bg-dark: #090e17;
            --card-dark: #131b2a;
            --border-dark: #1e2d42;
            --accent-teal: #06b6d4;
            --accent-green: #10b981;
        }
        body {
            background-color: var(--bg-dark);
            color: #f8fafc;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            overflow: hidden;
            height: 100vh;
            margin: 0;
            padding: 0;
            user-select: none;
        }
        .header-tv {
            height: 11vh;
            border-bottom: 2px solid var(--border-dark);
            padding: 0 3rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(19, 27, 42, 0.85);
            backdrop-filter: blur(10px);
        }
        .logo-tv {
            font-size: 2.2rem;
            font-weight: 800;
            letter-spacing: .05em;
            color: var(--accent-teal);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .clock-tv {
            font-size: 2.2rem;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            color: #e2e8f0;
        }
        .main-tv {
            height: 89vh;
            display: flex;
            padding: 2.5rem;
            gap: 2.5rem;
        }
        .current-box {
            flex: 1.5;
            background: linear-gradient(145deg, #131d2e, #0c1420);
            border: 2px solid var(--accent-teal);
            border-radius: 24px;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            box-shadow: 0 0 50px rgba(6, 182, 212, 0.15);
            position: relative;
            overflow: hidden;
        }
        .current-box.anim-flash {
            animation: pulse-glow 1.5s infinite alternate;
        }
        @keyframes pulse-glow {
            from { box-shadow: 0 0 40px rgba(6, 182, 212, 0.2); border-color: var(--accent-teal); }
            to { box-shadow: 0 0 80px rgba(16, 185, 129, 0.4); border-color: var(--accent-green); }
        }
        .badge-calling {
            background: rgba(6, 182, 212, 0.2);
            color: var(--accent-teal);
            font-size: 1.4rem;
            font-weight: 700;
            padding: 0.6rem 2rem;
            border-radius: 50px;
            letter-spacing: .1em;
            text-transform: uppercase;
            margin-bottom: 2rem;
            border: 1px solid var(--accent-teal);
        }
        .patient-name {
            font-size: 4.8rem;
            font-weight: 900;
            line-height: 1.1;
            color: #ffffff;
            margin-bottom: 2.5rem;
            text-shadow: 0 4px 20px rgba(0,0,0,0.5);
            max-width: 90%;
            word-wrap: break-word;
        }
        .dest-card {
            background: rgba(15, 23, 42, 0.8);
            border: 2px solid #334155;
            border-radius: 18px;
            padding: 1.5rem 3.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .dest-name {
            font-size: 3.2rem;
            font-weight: 800;
            color: var(--accent-green);
        }
        .doctor-name {
            font-size: 1.8rem;
            color: #94a3b8;
            margin-top: 1.5rem;
        }
        .history-box {
            flex: 1;
            background: var(--card-dark);
            border: 1px solid var(--border-dark);
            border-radius: 24px;
            padding: 2.5rem;
            display: flex;
            flex-direction: column;
        }
        .history-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: #94a3b8;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            border-bottom: 1px solid var(--border-dark);
            padding-bottom: 1rem;
        }
        .history-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-dark);
            border-radius: 14px;
            padding: 1.2rem 1.6rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .history-patient {
            font-size: 1.6rem;
            font-weight: 700;
            color: #e2e8f0;
        }
        .history-consultorio {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--accent-teal);
        }
        .history-time {
            font-size: 1.2rem;
            color: #64748b;
        }
        .audio-prompt {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <header class="header-tv">
        <div class="logo-tv">
            <i class="ti ti-dental"></i>
            <span>{{ $clinica->nombre ?? 'DENTAL PRO' }}</span>
        </div>
        <div class="clock-tv" id="tv-clock">00:00:00</div>
    </header>

    <main class="main-tv">
        {{-- Llamado Principal --}}
        <section class="current-box anim-flash" id="box-llamado">
            <div class="badge-calling" id="badge-llamado"><i class="ti ti-bell-ringing me-2"></i>Paciente Llamado</div>
            <div class="patient-name" id="p-nombre">Esperando próximo llamado...</div>
            <div class="dest-card" id="p-dest-container" style="display: none;">
                <i class="ti ti-door-enter text-success" style="font-size: 3rem;"></i>
                <div class="dest-name" id="p-dest">Consultorio 1</div>
            </div>
            <div class="doctor-name" id="p-doc" style="display: none;">Dr. Odontólogo</div>
        </section>

        {{-- Historial Reciente --}}
        <aside class="history-box">
            <div class="history-title">
                <i class="ti ti-history"></i>
                <span>Turnos Recientes</span>
            </div>
            <div id="lista-recientes" style="flex: 1; overflow: hidden;">
                <div class="text-center text-muted py-5" id="recientes-vacio">
                    <i class="ti ti-users-minus" style="font-size: 3rem;"></i>
                    <div class="mt-2 fs-3">Sin llamados previos hoy</div>
                </div>
            </div>
        </aside>
    </main>

    <div class="audio-prompt">
        <button id="btn-audio" class="btn btn-outline-info btn-lg rounded-pill shadow">
            <i class="ti ti-volume me-2"></i>Activar Audio &amp; Pantalla Completa
        </button>
    </div>

    <script>
        // Reloj digital en tiempo real
        function updateClock() {
            const now = new Date();
            document.getElementById('tv-clock').textContent = now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Síntesis de Audio Web (Chime de dos tonos: Ding-Dong 880Hz -> 587Hz)
        let audioCtx = null;
        function playChime() {
            try {
                if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                if (audioCtx.state === 'suspended') audioCtx.resume();

                const osc1 = audioCtx.createOscillator();
                const osc2 = audioCtx.createOscillator();
                const gain = audioCtx.createGain();

                osc1.type = 'sine';
                osc2.type = 'sine';
                osc1.frequency.setValueAtTime(880, audioCtx.currentTime); // La5
                osc2.frequency.setValueAtTime(587.33, audioCtx.currentTime + 0.35); // Re5

                gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 1.2);

                osc1.connect(gain);
                osc2.connect(gain);
                gain.connect(audioCtx.destination);

                osc1.start(audioCtx.currentTime);
                osc1.stop(audioCtx.currentTime + 0.35);

                osc2.start(audioCtx.currentTime + 0.35);
                osc2.stop(audioCtx.currentTime + 1.2);
            } catch (e) {
                console.warn("Audio Context aún no iniciado por usuario:", e);
            }
        }

        document.getElementById('btn-audio').addEventListener('click', () => {
            if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            audioCtx.resume();
            playChime();
            if (document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen().catch(() => {});
            }
            document.getElementById('btn-audio').style.display = 'none';
        });

        // Polling de turnos activos
        let ultimoTurnoId = null;

        async function fetchTurnos() {
            try {
                const res = await fetch("{{ route('admin.turnero.datos') }}", {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;
                const data = await res.json();

                if (data.actual) {
                    if (ultimoTurnoId !== data.actual.id) {
                        ultimoTurnoId = data.actual.id;
                        playChime();
                    }
                    document.getElementById('p-nombre').textContent = data.actual.paciente;
                    document.getElementById('p-dest').textContent = data.actual.consultorio;
                    document.getElementById('p-doc').textContent = data.actual.doctor;
                    document.getElementById('p-dest-container').style.display = 'flex';
                    document.getElementById('p-doc').style.display = 'block';
                }

                const recContainer = document.getElementById('lista-recientes');
                if (data.recientes && data.recientes.length > 0) {
                    recContainer.innerHTML = data.recientes.map(r => `
                        <div class="history-item">
                            <div>
                                <div class="history-patient">${r.paciente}</div>
                                <div class="history-consultorio"><i class="ti ti-door-enter me-1"></i>${r.consultorio}</div>
                            </div>
                            <div class="history-time"><i class="ti ti-clock me-1"></i>${r.hora}</div>
                        </div>
                    `).join('');
                }
            } catch (e) {
                console.error("Error al consultar datos de turnero:", e);
            }
        }

        setInterval(fetchTurnos, 3000);
        fetchTurnos();
    </script>
</body>
</html>
