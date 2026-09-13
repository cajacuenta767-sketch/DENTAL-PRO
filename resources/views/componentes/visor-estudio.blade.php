{{-- Visor modal de estudios: acercar, alejar, arrastrar y descargar original. --}}
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
                <div class="btn-list mb-3">
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
                    <a class="btn btn-sm btn-primary ms-auto" data-visor-descargar target="_blank" rel="noopener">
                        <i class="ti ti-download me-1"></i>Descargar original
                    </a>
                </div>

                <div class="border rounded bg-dark d-flex align-items-center justify-content-center"
                     data-visor-marco
                     style="height: 62vh; overflow: auto; cursor: grab;">
                    <img data-visor-imagen alt="Estudio radiográfico"
                         style="transform-origin: center center; transition: transform .12s ease; max-width: 100%;">
                </div>

                <div class="mt-3 d-none" data-visor-hallazgos-caja>
                    <div class="text-secondary small text-uppercase">Hallazgos</div>
                    <div data-visor-hallazgos></div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(() => {
    const modal = document.getElementById('visor-estudio');
    if (!modal) return;

    const imagen = modal.querySelector('[data-visor-imagen]');
    const marco = modal.querySelector('[data-visor-marco]');
    const nivel = modal.querySelector('[data-visor-nivel]');
    let escala = 1;

    function aplicar() {
        escala = Math.min(6, Math.max(0.25, escala));
        imagen.style.transform = `scale(${escala})`;
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
        arrastrando = true;
        marco.style.cursor = 'grabbing';
        inicioX = e.pageX; inicioY = e.pageY;
        scrollX = marco.scrollLeft; scrollY = marco.scrollTop;
    });

    ['mouseup', 'mouseleave'].forEach((evento) => marco.addEventListener(evento, () => {
        arrastrando = false;
        marco.style.cursor = 'grab';
    }));

    marco.addEventListener('mousemove', (e) => {
        if (!arrastrando) return;
        e.preventDefault();
        marco.scrollLeft = scrollX - (e.pageX - inicioX);
        marco.scrollTop = scrollY - (e.pageY - inicioY);
    });

    // Cada tarjeta de la galería abre el visor con sus propios datos.
    document.querySelectorAll('[data-estudio]').forEach((tarjeta) => {
        tarjeta.addEventListener('click', (e) => {
            if (e.target.closest('a, button, form')) return;

            const d = tarjeta.dataset;
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

            imagen.src = d.estudioUrl;
            escala = 1;
            aplicar();
            marco.scrollTo(0, 0);

            bootstrap.Modal.getOrCreateInstance(modal).show();
        });
    });
})();
</script>
@endpush
