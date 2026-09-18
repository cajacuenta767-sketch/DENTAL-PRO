{{-- Visor modal de estudios: acercar, alejar, arrastrar, anotar sobre la imagen y descargar original. --}}
<div class="modal modal-blur fade" id="visor-estudio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header align-items-start">
                <div>
                    <h3 class="modal-title mb-1" data-visor-titulo>Estudio</h3>
                    <div class="text-secondary small" data-visor-fecha></div>
                    <div class="text-secondary small text-truncate" data-visor-archivo style="max-width: 46rem;"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                <div class="btn-list mb-2">
                    <button type="button" class="btn btn-sm" data-visor-zoom="-0.25">
                        <i class="ti ti-zoom-out me-1"></i>Alejar
                    </button>
                    <button type="button" class="btn btn-sm" data-visor-zoom="0.25">
                        <i class="ti ti-zoom-in me-1"></i>Acercar
                    </button>
                    <button type="button" class="btn btn-sm" data-visor-zoom="reset">
                        <i class="ti ti-maximize me-1"></i>Ajustar
                    </button>
                    <span class="btn btn-sm disabled" data-visor-nivel>100%</span>
                    <label class="form-check form-switch mb-0 ms-2 align-self-center" title="Mostrar u ocultar las anotaciones dibujadas">
                        <input class="form-check-input" type="checkbox" checked data-anot-mostrar>
                        <span class="form-check-label small">Mostrar anotaciones</span>
                    </label>
                    <a class="btn btn-sm btn-primary ms-auto" data-visor-descargar target="_blank" rel="noopener">
                        <i class="ti ti-download me-1"></i>Descargar original
                    </a>
                </div>

                @can('imagenologia.editar')
                    {{-- Herramientas de anotación: dibujan sobre un canvas superpuesto a la imagen. --}}
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2 p-2 border rounded bg-light" data-anot-herramientas>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Herramienta">
                            <button type="button" class="btn active" data-anot-modo="mover" title="Mover (arrastrar la imagen)"><i class="ti ti-hand-move"></i></button>
                            <button type="button" class="btn" data-anot-modo="lapiz" title="Lápiz"><i class="ti ti-pencil"></i></button>
                            <button type="button" class="btn" data-anot-modo="flecha" title="Flecha"><i class="ti ti-arrow-up-right"></i></button>
                            <button type="button" class="btn" data-anot-modo="circulo" title="Círculo"><i class="ti ti-circle"></i></button>
                            <button type="button" class="btn" data-anot-modo="texto" title="Texto"><i class="ti ti-typography"></i></button>
                        </div>
                        <div class="d-flex align-items-center gap-1" role="group" aria-label="Color">
                            @foreach (['#d63939' => 'Rojo', '#f59f00' => 'Amarillo', '#2fb344' => 'Verde', '#206bc4' => 'Azul'] as $hex => $nombre)
                                <button type="button" class="btn btn-sm p-0 border rounded-circle {{ $loop->first ? 'anot-color-activo' : '' }}"
                                        data-anot-color="{{ $hex }}" title="{{ $nombre }}"
                                        style="width: 1.5rem; height: 1.5rem; background: {{ $hex }};"></button>
                            @endforeach
                        </div>
                        <label class="d-flex align-items-center gap-1 small mb-0">
                            Grosor
                            <input type="range" class="form-range" min="1" max="12" value="3" style="width: 6rem;" data-anot-grosor>
                        </label>
                        <div class="btn-list ms-auto">
                            <button type="button" class="btn btn-sm" data-anot-deshacer><i class="ti ti-arrow-back-up me-1"></i>Deshacer</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-anot-borrar><i class="ti ti-eraser me-1"></i>Borrar todo</button>
                            <button type="button" class="btn btn-sm btn-success" data-anot-guardar><i class="ti ti-device-floppy me-1"></i>Guardar anotaciones</button>
                        </div>
                        <div class="w-100 small text-secondary d-none" data-anot-estado></div>
                    </div>
                @endcan

                <div class="border rounded bg-dark d-flex align-items-center justify-content-center"
                     data-visor-marco
                     style="height: 62vh; overflow: auto; cursor: grab;">
                    <div data-visor-lienzo style="position: relative; display: inline-block; transform-origin: center center; transition: transform .12s ease; max-width: 100%; line-height: 0;">
                        <img data-visor-imagen alt="Estudio radiográfico" style="max-width: 100%; display: block;">
                        <canvas data-anot-canvas style="position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none;"></canvas>
                    </div>
                    {{-- Los PDF se incrustan; los demás documentos (Word, Excel, DICOM…) solo se descargan. --}}
                    <iframe data-visor-pdf title="Documento PDF" class="d-none w-100 h-100 border-0 bg-white"></iframe>
                    <div data-visor-documento class="d-none text-center text-white p-4">
                        <i class="ti ti-file fs-1 d-block mb-2" data-visor-documento-icono></i>
                        <div class="fw-medium mb-1" data-visor-documento-nombre></div>
                        <div class="text-white-50 small mb-3">Este tipo de archivo no se previsualiza en el navegador.</div>
                        <a class="btn btn-primary" data-visor-documento-descarga target="_blank" rel="noopener">
                            <i class="ti ti-download me-1"></i>Descargar
                        </a>
                    </div>
                </div>

                <div class="mt-3 d-none" data-visor-hallazgos-caja>
                    <div class="text-secondary small text-uppercase">Hallazgos</div>
                    <div data-visor-hallazgos></div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('head')
<style>
    #visor-estudio [data-anot-color].anot-color-activo { outline: 3px solid #1d273b; outline-offset: 2px; }
    #visor-estudio [data-anot-canvas].anot-dibujando { pointer-events: auto; cursor: crosshair; touch-action: none; }
</style>
@endpush

@push('scripts')
<script>
(() => {
    const modal = document.getElementById('visor-estudio');
    if (!modal) return;

    const imagen = modal.querySelector('[data-visor-imagen]');
    const lienzo = modal.querySelector('[data-visor-lienzo]');
    const marco = modal.querySelector('[data-visor-marco]');
    const visorPdf = modal.querySelector('[data-visor-pdf]');
    const visorDocumento = modal.querySelector('[data-visor-documento]');
    const nivel = modal.querySelector('[data-visor-nivel]');
    const canvas = modal.querySelector('[data-anot-canvas]');
    const ctx = canvas.getContext('2d');
    const herramientas = modal.querySelector('[data-anot-herramientas]');
    const mostrar = modal.querySelector('[data-anot-mostrar]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    let escala = 1;
    let tarjetaActual = null;
    let anotaciones = [];      // coordenadas relativas 0-1 a la imagen
    let modo = 'mover';
    let color = '#d63939';
    let grosor = 3;
    let trazo = null;          // anotación en curso

    function aplicar() {
        escala = Math.min(6, Math.max(0.25, escala));
        lienzo.style.transform = `scale(${escala})`;
        nivel.textContent = `${Math.round(escala * 100)}%`;
    }

    modal.querySelectorAll('[data-visor-zoom]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const paso = boton.dataset.visorZoom;
            escala = paso === 'reset' ? 1 : escala + Number(paso);
            aplicar();
        });
    });

    // Arrastrar para desplazarse cuando la imagen supera el marco.
    let arrastrando = false, inicioX = 0, inicioY = 0, scrollX = 0, scrollY = 0;

    marco.addEventListener('mousedown', (e) => {
        if (modo !== 'mover') return;
        arrastrando = true;
        marco.style.cursor = 'grabbing';
        inicioX = e.pageX; inicioY = e.pageY;
        scrollX = marco.scrollLeft; scrollY = marco.scrollTop;
    });

    ['mouseup', 'mouseleave'].forEach((evento) => marco.addEventListener(evento, () => {
        arrastrando = false;
        marco.style.cursor = modo === 'mover' ? 'grab' : 'default';
    }));

    marco.addEventListener('mousemove', (e) => {
        if (!arrastrando) return;
        e.preventDefault();
        marco.scrollLeft = scrollX - (e.pageX - inicioX);
        marco.scrollTop = scrollY - (e.pageY - inicioY);
    });

    // ---- Capa de anotaciones -------------------------------------------------

    function ajustarCanvas() {
        const w = imagen.clientWidth || imagen.naturalWidth || 1;
        const h = imagen.clientHeight || imagen.naturalHeight || 1;
        const ratio = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = Math.round(w * ratio);
        canvas.height = Math.round(h * ratio);
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        redibujar();
    }

    imagen.addEventListener('load', ajustarCanvas);
    window.addEventListener('resize', () => { if (modal.classList.contains('show')) ajustarCanvas(); });

    function dibujarUna(a) {
        const W = imagen.clientWidth || 1;
        const H = imagen.clientHeight || 1;
        ctx.strokeStyle = a.color;
        ctx.fillStyle = a.color;
        ctx.lineWidth = a.grosor;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        if (a.tipo === 'lapiz') {
            if (!a.puntos || a.puntos.length === 0) return;
            ctx.beginPath();
            a.puntos.forEach(([x, y], i) => (i === 0 ? ctx.moveTo(x * W, y * H) : ctx.lineTo(x * W, y * H)));
            ctx.stroke();
        } else if (a.tipo === 'flecha') {
            const x1 = a.desde.x * W, y1 = a.desde.y * H, x2 = a.hasta.x * W, y2 = a.hasta.y * H;
            ctx.beginPath(); ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.stroke();
            const ang = Math.atan2(y2 - y1, x2 - x1);
            const largo = 8 + a.grosor * 2.5;
            ctx.beginPath();
            ctx.moveTo(x2, y2);
            ctx.lineTo(x2 - largo * Math.cos(ang - Math.PI / 6), y2 - largo * Math.sin(ang - Math.PI / 6));
            ctx.lineTo(x2 - largo * Math.cos(ang + Math.PI / 6), y2 - largo * Math.sin(ang + Math.PI / 6));
            ctx.closePath();
            ctx.fill();
        } else if (a.tipo === 'circulo') {
            ctx.beginPath();
            ctx.arc(a.centro.x * W, a.centro.y * H, Math.max(1, a.radio * W), 0, Math.PI * 2);
            ctx.stroke();
        } else if (a.tipo === 'texto') {
            ctx.font = `bold ${10 + a.grosor * 3}px sans-serif`;
            ctx.lineWidth = 3;
            ctx.strokeStyle = 'rgba(0,0,0,.65)';
            ctx.strokeText(a.texto, a.x * W, a.y * H);
            ctx.fillText(a.texto, a.x * W, a.y * H);
        }
    }

    function redibujar() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (!mostrar.checked) return;
        anotaciones.forEach(dibujarUna);
        if (trazo) dibujarUna(trazo);
    }

    mostrar.addEventListener('change', redibujar);

    function relativo(e) {
        const r = canvas.getBoundingClientRect();
        return {
            x: Math.min(1, Math.max(0, (e.clientX - r.left) / r.width)),
            y: Math.min(1, Math.max(0, (e.clientY - r.top) / r.height)),
        };
    }

    function elegirModo(nuevo) {
        modo = nuevo;
        herramientas?.querySelectorAll('[data-anot-modo]').forEach((b) => b.classList.toggle('active', b.dataset.anotModo === nuevo));
        canvas.classList.toggle('anot-dibujando', nuevo !== 'mover');
        marco.style.cursor = nuevo === 'mover' ? 'grab' : 'default';
    }

    function estado(texto, clase = 'text-secondary') {
        const caja = herramientas?.querySelector('[data-anot-estado]');
        if (!caja) return;
        caja.className = `w-100 small ${clase}`;
        caja.textContent = texto;
        caja.classList.toggle('d-none', !texto);
    }

    if (herramientas) {
        herramientas.querySelectorAll('[data-anot-modo]').forEach((b) => b.addEventListener('click', () => elegirModo(b.dataset.anotModo)));

        herramientas.querySelectorAll('[data-anot-color]').forEach((b) => b.addEventListener('click', () => {
            color = b.dataset.anotColor;
            herramientas.querySelectorAll('[data-anot-color]').forEach((o) => o.classList.toggle('anot-color-activo', o === b));
        }));

        herramientas.querySelector('[data-anot-grosor]').addEventListener('input', (e) => { grosor = Number(e.target.value) || 3; });

        herramientas.querySelector('[data-anot-deshacer]').addEventListener('click', () => { anotaciones.pop(); redibujar(); });

        herramientas.querySelector('[data-anot-borrar]').addEventListener('click', () => {
            if (anotaciones.length === 0 || confirm('¿Borrar todas las anotaciones de este estudio?')) {
                anotaciones = [];
                redibujar();
            }
        });

        herramientas.querySelector('[data-anot-guardar]').addEventListener('click', async () => {
            if (!tarjetaActual?.dataset.estudioAnotacionesUrl) return;
            estado('Guardando…');
            try {
                const respuesta = await fetch(tarjetaActual.dataset.estudioAnotacionesUrl, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ anotaciones }),
                });
                const cuerpo = await respuesta.json().catch(() => ({}));
                if (!respuesta.ok || !cuerpo.ok) throw new Error(cuerpo.message || 'No se pudieron guardar las anotaciones.');

                tarjetaActual.dataset.anotaciones = JSON.stringify(anotaciones);
                tarjetaActual.querySelector('[data-estudio-badge-anotado]')?.classList.toggle('d-none', anotaciones.length === 0);
                estado(`Anotaciones guardadas (${anotaciones.length}).`, 'text-success');
            } catch (error) {
                estado(error.message, 'text-danger');
            }
        });

        canvas.addEventListener('pointerdown', (e) => {
            if (modo === 'mover') return;
            e.preventDefault();
            const p = relativo(e);

            if (modo === 'texto') {
                const texto = prompt('Texto de la anotación:');
                if (texto && texto.trim() !== '') {
                    anotaciones.push({ tipo: 'texto', color, grosor, texto: texto.trim().slice(0, 200), x: p.x, y: p.y });
                    redibujar();
                }
                return;
            }

            canvas.setPointerCapture(e.pointerId);
            if (modo === 'lapiz') trazo = { tipo: 'lapiz', color, grosor, puntos: [[p.x, p.y]] };
            if (modo === 'flecha') trazo = { tipo: 'flecha', color, grosor, desde: { ...p }, hasta: { ...p } };
            if (modo === 'circulo') trazo = { tipo: 'circulo', color, grosor, centro: { ...p }, radio: 0 };
            redibujar();
        });

        canvas.addEventListener('pointermove', (e) => {
            if (!trazo) return;
            const p = relativo(e);
            if (trazo.tipo === 'lapiz') {
                const ultimo = trazo.puntos[trazo.puntos.length - 1];
                if (Math.hypot(p.x - ultimo[0], p.y - ultimo[1]) > 0.002) trazo.puntos.push([p.x, p.y]);
            } else if (trazo.tipo === 'flecha') {
                trazo.hasta = { ...p };
            } else if (trazo.tipo === 'circulo') {
                trazo.radio = Math.hypot(p.x - trazo.centro.x, (p.y - trazo.centro.y) * ((imagen.clientHeight || 1) / (imagen.clientWidth || 1)));
            }
            redibujar();
        });

        const terminar = () => {
            if (!trazo) return;
            const valido = trazo.tipo === 'lapiz' ? trazo.puntos.length > 1
                : trazo.tipo === 'circulo' ? trazo.radio > 0.002
                : Math.hypot(trazo.hasta.x - trazo.desde.x, trazo.hasta.y - trazo.desde.y) > 0.002;
            if (valido) anotaciones.push(trazo);
            trazo = null;
            redibujar();
        };
        canvas.addEventListener('pointerup', terminar);
        canvas.addEventListener('pointercancel', terminar);
    }

    // Cada tarjeta de la galería abre el visor con sus propios datos.
    document.querySelectorAll('[data-estudio]').forEach((tarjeta) => {
        tarjeta.addEventListener('click', (e) => {
            if (e.target.closest('a, button, form, label, input')) return;

            const d = tarjeta.dataset;
            tarjetaActual = tarjeta;
            modal.querySelector('[data-visor-titulo]').textContent = `${d.estudioTitulo} — ${d.estudioFecha}`;
            modal.querySelector('[data-visor-fecha]').textContent = `${d.estudioTipo} · Estudio del ${d.estudioIso}`;
            modal.querySelector('[data-visor-archivo]').textContent = d.estudioArchivo || '';
            modal.querySelector('[data-visor-descargar]').href = d.estudioDescarga;

            const caja = modal.querySelector('[data-visor-hallazgos-caja]');
            if (d.estudioHallazgos) {
                modal.querySelector('[data-visor-hallazgos]').textContent = d.estudioHallazgos;
                caja.classList.remove('d-none');
            } else {
                caja.classList.add('d-none');
            }

            try { anotaciones = JSON.parse(d.anotaciones || '[]'); } catch { anotaciones = []; }
            if (!Array.isArray(anotaciones)) anotaciones = [];
            trazo = null;
            estado('');
            elegirModo('mover');

            // Solo las imágenes se dibujan y amplían: PDF va incrustado, el resto se descarga.
            const esImagen = d.estudioVisualizable !== '0';
            const esPdf = !esImagen && d.estudioPdf === '1';
            herramientas?.classList.toggle('d-none', !esImagen);
            canvas.classList.toggle('d-none', !esImagen);
            lienzo.classList.toggle('d-none', !esImagen);
            modal.querySelectorAll('[data-visor-zoom], [data-visor-nivel], [data-anot-mostrar]').forEach((b) => b.closest('label, button, span')?.classList.toggle('d-none', !esImagen));
            visorPdf.classList.toggle('d-none', !esPdf);
            visorPdf.src = esPdf ? d.estudioUrl : 'about:blank';
            visorDocumento.classList.toggle('d-none', esImagen || esPdf);
            if (!esImagen && !esPdf) {
                const iconos = { documento: 'ti-file-text', hoja: 'ti-file-spreadsheet', dicom: 'ti-radioactive', imagen: 'ti-photo' };
                visorDocumento.querySelector('[data-visor-documento-icono]').className = `ti ${iconos[d.estudioFamilia] ?? 'ti-file'} fs-1 d-block mb-2`;
                visorDocumento.querySelector('[data-visor-documento-nombre]').textContent = d.estudioArchivo;
                visorDocumento.querySelector('[data-visor-documento-descarga]').href = d.estudioDescarga;
            }

            imagen.src = esImagen ? d.estudioUrl : '';
            escala = 1;
            aplicar();
            marco.scrollTo(0, 0);
            if (imagen.complete) ajustarCanvas();

            bootstrap.Modal.getOrCreateInstance(modal).show();
        });
    });

    modal.addEventListener('shown.bs.modal', ajustarCanvas);
})();
</script>
@endpush
