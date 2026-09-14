/**
 * Odontograma en vista de arcada (desde arriba). Dibuja cada pieza como una
 * corona vista desde oclusal, orientada siguiendo la curva de la arcada, con
 * sus cinco caras clicables. Los nodos generados llevan los mismos atributos
 * data-pieza / data-cara que la cuadrícula clásica, así que el mismo código
 * de pintado sirve para las dos vistas.
 */

const CARAS = ['vestibular', 'lingual', 'mesial', 'distal', 'oclusal'];

/** Ancho (mesio-distal) y alto (vestíbulo-lingual) por posición en el cuadrante. */
const TAMANOS = {
    ADULTO: {
        superior: { 1: [27, 24], 2: [23, 22], 3: [26, 27], 4: [27, 30], 5: [27, 30], 6: [38, 35], 7: [36, 34], 8: [32, 31] },
        inferior: { 1: [20, 22], 2: [22, 22], 3: [24, 27], 4: [26, 29], 5: [27, 30], 6: [39, 34], 7: [37, 33], 8: [33, 31] },
    },
    INFANTIL: {
        superior: { 1: [24, 21], 2: [20, 19], 3: [23, 24], 4: [29, 29], 5: [32, 30] },
        inferior: { 1: [19, 19], 2: [20, 19], 3: [22, 23], 4: [29, 29], 5: [33, 30] },
    },
};

const CUADRANTES = {
    ADULTO: {
        superior: [[18, 17, 16, 15, 14, 13, 12, 11], [21, 22, 23, 24, 25, 26, 27, 28]],
        inferior: [[48, 47, 46, 45, 44, 43, 42, 41], [31, 32, 33, 34, 35, 36, 37, 38]],
    },
    INFANTIL: {
        superior: [[55, 54, 53, 52, 51], [61, 62, 63, 64, 65]],
        inferior: [[85, 84, 83, 82, 81], [71, 72, 73, 74, 75]],
    },
};

const NS = 'http://www.w3.org/2000/svg';

function el(nombre, atributos = {}, hijos = []) {
    const nodo = document.createElementNS(NS, nombre);
    Object.entries(atributos).forEach(([k, v]) => nodo.setAttribute(k, v));
    hijos.forEach((h) => nodo.appendChild(h));
    return nodo;
}

const f = (n) => Number(n.toFixed(2));

/** Caras de una corona vista desde arriba, en coordenadas locales (0,0 = centro). */
function carasDeCorona(w, h, mesialADerecha) {
    const x = w / 2;
    const y = h / 2;
    const kx = x * 0.5;
    const ky = y * 0.5;
    const r = Math.min(x, y) * 0.5; // redondeo de las esquinas exteriores

    // Contorno exterior redondeado, por tramos, para que las caras compartan bordes.
    const esquina = (x1, y1, x2, y2, cx, cy) => `Q ${f(cx)} ${f(cy)} ${f(x2)} ${f(y2)}`;

    const vestibular = `M ${f(-x + r)} ${f(-y)} L ${f(x - r)} ${f(-y)} ${esquina(x - r, -y, x, -y + r, x, -y)} L ${f(kx)} ${f(-ky)} L ${f(-kx)} ${f(-ky)} L ${f(-x)} ${f(-y + r)} ${esquina(-x, -y + r, -x + r, -y, -x, -y)} Z`;
    const lingual = `M ${f(-x)} ${f(y - r)} L ${f(-kx)} ${f(ky)} L ${f(kx)} ${f(ky)} L ${f(x)} ${f(y - r)} ${esquina(x, y - r, x - r, y, x, y)} L ${f(-x + r)} ${f(y)} ${esquina(-x + r, y, -x, y - r, -x, y)} Z`;
    const derecha = `M ${f(x)} ${f(-y + r)} L ${f(x)} ${f(y - r)} L ${f(kx)} ${f(ky)} L ${f(kx)} ${f(-ky)} Z`;
    const izquierda = `M ${f(-x)} ${f(-y + r)} L ${f(-kx)} ${f(-ky)} L ${f(-kx)} ${f(ky)} L ${f(-x)} ${f(y - r)} Z`;
    const oclusal = `M ${f(-kx)} ${f(-ky)} L ${f(kx)} ${f(-ky)} L ${f(kx)} ${f(ky)} L ${f(-kx)} ${f(ky)} Z`;

    return {
        vestibular,
        lingual,
        mesial: mesialADerecha ? derecha : izquierda,
        distal: mesialADerecha ? izquierda : derecha,
        oclusal,
    };
}

/** Surcos decorativos de la cara oclusal de molares y premolares. */
function surcos(w, h, posicion) {
    if (posicion < 4) return null;
    const kx = w / 4;
    const ky = h / 4;
    const d = posicion >= 6
        ? `M ${f(-kx * 0.8)} ${f(-ky * 0.7)} L 0 0 L ${f(kx * 0.8)} ${f(-ky * 0.7)} M ${f(-kx * 0.8)} ${f(ky * 0.7)} L 0 0 L ${f(kx * 0.8)} ${f(ky * 0.7)} M ${f(-kx)} 0 L ${f(kx)} 0`
        : `M ${f(-kx * 0.7)} 0 L ${f(kx * 0.7)} 0`;

    return el('path', { d, class: 'pieza-surco' });
}

/**
 * Construye el SVG de las dos arcadas dentro del contenedor.
 *
 * @param {HTMLElement} contenedor  Nodo con data-arcada y data-tipo.
 * @param {{ tipo?: string, numeros?: boolean }} opciones
 * @returns {SVGSVGElement}
 */
export function montarArcada(contenedor, opciones = {}) {
    const tipo = opciones.tipo ?? contenedor.dataset.tipo ?? 'ADULTO';
    const cfg = CUADRANTES[tipo] ?? CUADRANTES.ADULTO;
    const tamanos = TAMANOS[tipo] ?? TAMANOS.ADULTO;
    const infantil = tipo === 'INFANTIL';

    const ancho = 480;
    const alto = 640;
    const cx = ancho / 2;
    const rx = infantil ? 130 : 168;   // semiancho de la arcada a la altura de los últimos molares
    const ry = infantil ? 190 : 245;   // profundidad de la arcada
    const apiceSuperior = 46;
    const apiceInferior = alto - 46;

    const svg = el('svg', {
        viewBox: `0 0 ${ancho} ${alto}`,
        class: 'odontograma-arcada-svg',
        role: 'img',
        'aria-label': 'Odontograma en vista de arcada',
    });

    /** Puntos de una parábola (la forma real de la arcada) con espaciado uniforme por longitud. */
    function repartir(n, superior) {
        const muestras = [];
        const pasos = 600;

        for (let k = 0; k <= pasos; k++) {
            const t = -1 + (2 * k) / pasos;
            const x = cx + rx * t;
            const y = superior ? apiceSuperior + ry * t * t : apiceInferior - ry * t * t;
            muestras.push([x, y]);
        }

        const acumulada = [0];
        for (let k = 1; k < muestras.length; k++) {
            acumulada.push(acumulada[k - 1] + Math.hypot(muestras[k][0] - muestras[k - 1][0], muestras[k][1] - muestras[k - 1][1]));
        }
        const total = acumulada[acumulada.length - 1];
        const foco = [cx, superior ? apiceSuperior + ry * 0.55 : apiceInferior - ry * 0.55];

        return Array.from({ length: n }, (_, i) => {
            const objetivo = ((i + 0.5) * total) / n;
            let k = acumulada.findIndex((a) => a >= objetivo);
            k = Math.min(Math.max(k, 1), muestras.length - 2);

            const [px, py] = muestras[k];
            let [tx, ty] = [muestras[k + 1][0] - muestras[k - 1][0], muestras[k + 1][1] - muestras[k - 1][1]];
            const nt = Math.hypot(tx, ty) || 1;
            tx /= nt; ty /= nt;

            // Normal perpendicular a la tangente, apuntando hacia fuera de la arcada.
            let [nx, ny] = [-ty, tx];
            if ((px - foco[0]) * nx + (py - foco[1]) * ny < 0) { nx = -nx; ny = -ny; }

            return { px, py, tx, ty, nx, ny };
        });
    }

    // Paladar y lengua: la misma parábola, reducida, como referencia visual.
    const fondo = (superior) => {
        const k = 0.66;
        const a = superior ? apiceSuperior + ry * 0.14 : apiceInferior - ry * 0.14;
        const partes = [];
        for (let i = 0; i <= 40; i++) {
            const t = -1 + i / 20;
            partes.push(`${f(cx + rx * k * t)} ${f(superior ? a + ry * k * t * t : a - ry * k * t * t)}`);
        }
        return el('path', { d: `M ${partes.join(' L ')} Z`, class: 'arcada-fondo' });
    };
    svg.appendChild(fondo(true));
    svg.appendChild(fondo(false));

    svg.appendChild(el('text', { x: cx, y: apiceSuperior + ry * 0.62, class: 'arcada-etiqueta', 'text-anchor': 'middle' }, [document.createTextNode('SUPERIOR')]));
    svg.appendChild(el('text', { x: cx, y: apiceInferior - ry * 0.62 + 4, class: 'arcada-etiqueta', 'text-anchor': 'middle' }, [document.createTextNode('INFERIOR')]));
    svg.appendChild(el('text', { x: 14, y: alto / 2 + 4, class: 'arcada-lado' }, [document.createTextNode('DER.')]));
    svg.appendChild(el('text', { x: ancho - 14, y: alto / 2 + 4, class: 'arcada-lado', 'text-anchor': 'end' }, [document.createTextNode('IZQ.')]));

    ['superior', 'inferior'].forEach((arcada) => {
        const [izq, der] = cfg[arcada];
        const piezas = [...izq, ...der];
        const n = piezas.length;
        const posiciones = repartir(n, arcada === 'superior');

        piezas.forEach((numero, i) => {
            const { px, py, tx, ty, nx, ny } = posiciones[i];
            const posicion = Number(String(numero).slice(-1));
            const [w, h] = tamanos[arcada][posicion] ?? [24, 24];
            const mesialADerecha = i < n / 2; // cuadrantes 1 y 4: la línea media queda a la derecha
            const caras = carasDeCorona(w, h, mesialADerecha);

            const grupo = el('g', {
                class: 'pieza pieza-arcada',
                'data-pieza': numero,
                'data-estado': 'sano',
                // Eje x local = tangente (de izquierda a derecha); eje -y local = hacia fuera (vestibular).
                transform: `matrix(${f(tx)} ${f(ty)} ${f(-nx)} ${f(-ny)} ${f(px)} ${f(py)})`,
                tabindex: '0',
                role: 'button',
                'aria-label': `Pieza ${numero}`,
            });

            grupo.appendChild(el('rect', {
                x: -w / 2 - 3, y: -h / 2 - 3, width: w + 6, height: h + 6, rx: 7,
                class: 'pieza-anillo', 'data-anillo': '1',
            }));

            CARAS.forEach((cara) => {
                grupo.appendChild(el('path', { d: caras[cara], class: 'pieza-cara', 'data-cara': cara, fill: '#ffffff', style: 'fill: #ffffff' }));
            });

            const surco = surcos(w, h, posicion);
            if (surco) grupo.appendChild(surco);

            grupo.appendChild(el('line', { x1: -w / 2 - 2, y1: 0, x2: w / 2 + 2, y2: 0, class: 'pieza-tachado', 'data-tachado': '1' }));
            grupo.appendChild(el('line', { x1: 0, y1: -h / 2 - 2, x2: 0, y2: h / 2 + 2, class: 'pieza-tachado', 'data-tachado': '1' }));

            svg.appendChild(grupo);

            // Número fuera de la arcada, siempre derecho.
            const lx = px + nx * (h / 2 + 13);
            const ly = py + ny * (h / 2 + 13);
            svg.appendChild(el('text', {
                x: f(lx), y: f(ly + 4), class: 'pieza-etiqueta', 'text-anchor': 'middle', 'data-etiqueta': numero,
            }, [document.createTextNode(String(numero))]));
        });
    });

    contenedor.innerHTML = '';
    contenedor.appendChild(svg);

    return svg;
}

/**
 * Pinta un mapa de piezas sobre cualquier conjunto de nodos [data-pieza]
 * (cuadrícula o arcada). Devuelve el conteo por capa.
 */
export function pintarMapa(raiz, mapa, estados, opciones = {}) {
    const capa = opciones.capa ?? 'todos';
    const seleccionada = opciones.seleccionada ?? null;
    const resaltar = opciones.resaltar ?? null;

    const visible = (clave) => clave === 'sano' || capa === 'todos' || estados[clave]?.capa === capa;
    const color = (clave) => (visible(clave) ? estados[clave]?.color : '#ffffff') ?? '#ffffff';

    raiz.querySelectorAll('[data-pieza]').forEach((nodo) => {
        const pieza = mapa[nodo.dataset.pieza] ?? { estado: 'sano', caras: {} };
        const claves = [pieza.estado, ...Object.values(pieza.caras ?? {})];

        nodo.dataset.estado = pieza.estado;
        nodo.classList.toggle('pieza-ausente', pieza.estado === 'ausente' && visible('ausente'));
        nodo.classList.toggle('pieza-extraccion', pieza.estado === 'extraccion' && visible('extraccion'));
        nodo.classList.toggle('pieza-activa', nodo.dataset.pieza === String(seleccionada));
        nodo.classList.toggle('pieza-urgente', Boolean(pieza.urgente));
        nodo.classList.toggle('pieza-atenuada', Boolean(resaltar) && !claves.includes(resaltar));

        nodo.querySelectorAll('[data-cara]').forEach((cara) => {
            const clave = pieza.estado !== 'sano' ? pieza.estado : (pieza.caras?.[cara.dataset.cara] ?? 'sano');
            const relleno = color(clave);
            cara.setAttribute('fill', relleno);
            cara.style.fill = relleno;
        });
    });

    raiz.querySelectorAll('[data-etiqueta]').forEach((texto) => {
        texto.classList.toggle('pieza-etiqueta-activa', texto.dataset.etiqueta === String(seleccionada));
    });
}

/** Monta en modo lectura todas las arcadas de la página con su mapa embebido. */
function montarLecturas() {
    document.querySelectorAll('[data-arcada][data-piezas]').forEach((contenedor) => {
        if (contenedor.dataset.montada) return;

        let mapa = {};
        let estados = {};
        try { mapa = JSON.parse(contenedor.dataset.piezas || '{}'); } catch { /* vacío */ }
        try { estados = JSON.parse(contenedor.dataset.estados || '{}'); } catch { /* vacío */ }

        montarArcada(contenedor);
        pintarMapa(contenedor, mapa, estados);
        contenedor.dataset.montada = '1';
    });
}

window.OdontoSuite = Object.assign(window.OdontoSuite ?? {}, { montarArcada, pintarMapa, CARAS_ODONTOGRAMA: CARAS });

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', montarLecturas);
} else {
    montarLecturas();
}
