import './bootstrap';

import * as tabler from '@tabler/core/dist/js/tabler.min.js';
import ApexCharts from 'apexcharts';
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.bootstrap5.css';
import './odontograma';

// Tabler empaqueta Bootstrap en formato UMD: al importarlo como módulo no deja el global
// `bootstrap` que usan los visores (modales abiertos por script). Se expone aquí.
window.bootstrap = window.bootstrap ?? tabler.default ?? tabler;
window.ApexCharts = ApexCharts;
window.TomSelect = TomSelect;

/**
 * Selectores de paciente con búsqueda remota. En lugar de volcar todos los
 * pacientes en el <select>, se consultan a medida que el usuario escribe.
 */
function escapar(texto) {
    return String(texto ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[c]);
}

function montarSelectorPaciente(select) {
    if (select.tomselect) return select.tomselect;

    const url = select.dataset.url;

    // La <option> preseleccionada (si la hay) la lee Tom Select del propio DOM.
    const ts = new TomSelect(select, {
        valueField: 'id',
        labelField: 'texto',
        searchField: ['texto'],
        placeholder: select.dataset.placeholder || 'Escribe nombre o documento',
        maxOptions: 20,
        loadThrottle: 300,
        preload: false,
        allowEmptyOption: false,
        closeAfterSelect: true,
        shouldLoad: (consulta) => consulta.trim().length >= 2,
        load(consulta, callback) {
            const destino = new URL(url, window.location.origin);
            destino.searchParams.set('q', consulta.trim());

            fetch(destino, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then((r) => (r.ok ? r.json() : Promise.reject(new Error(r.statusText))))
                .then((datos) => callback(Array.isArray(datos) ? datos : []))
                .catch(() => callback());
        },
        render: {
            option: (d) => `<div>
                <div>${escapar(d.texto)}</div>
                ${d.telefono ? `<div class="text-secondary small"><i class="ti ti-phone me-1"></i>${escapar(d.telefono)}</div>` : ''}
            </div>`,
            item: (d) => `<div>${escapar(d.texto)}</div>`,
            no_results: () => '<div class="no-results">Sin coincidencias.</div>',
            loading: () => '<div class="spinner"></div>',
            not_loading: (data) => (data.input.trim().length < 2
                ? '<div class="no-results text-secondary">Escribe al menos 2 caracteres.</div>'
                : ''),
        },
    });

    // Copia los datos extra (cobertura, aseguradora…) a la <option> real para
    // que los scripts de cada formulario sigan leyendo option.dataset.
    // Se registra en fase de captura para adelantarse a los demás oyentes.
    select.addEventListener('change', () => {
        const opcion = select.selectedOptions[0];
        const datos = opcion ? ts.options[opcion.value] : null;
        if (!opcion || !datos) return;

        Object.entries(datos).forEach(([clave, valor]) => {
            if (['id', 'texto', '$order', '$id', '$option', '$div'].includes(clave)) return;
            if (valor === null || typeof valor === 'object') return;
            opcion.dataset[clave] = valor;
        });
    }, true);

    return ts;
}

function montarSelectoresPaciente(raiz = document) {
    raiz.querySelectorAll('select[data-selector-paciente]').forEach(montarSelectorPaciente);
}

/** Envuelve en .table-responsive las tablas que no lo estén (scroll horizontal en móvil). */
function hacerTablasResponsivas(raiz = document) {
    raiz.querySelectorAll('table.table').forEach((tabla) => {
        if (tabla.closest('.table-responsive') || tabla.closest('.dataTables_wrapper')) return;

        const envoltorio = document.createElement('div');
        envoltorio.className = 'table-responsive';
        tabla.parentNode.insertBefore(envoltorio, tabla);
        envoltorio.appendChild(tabla);
    });
}

/**
 * Conmutador de tema claro / oscuro. Tabler lee el atributo
 * data-bs-theme del <html>; aquí sólo lo persistimos.
 */
const TEMA_CLAVE = 'odontosuite-tema';

function aplicarTema(tema) {
    document.documentElement.setAttribute('data-bs-theme', tema);
    try {
        localStorage.setItem(TEMA_CLAVE, tema);
    } catch {
        /* almacenamiento no disponible: el tema dura sólo esta visita */
    }
}

window.OdontoSuite = Object.assign(window.OdontoSuite ?? {}, {
    aplicarTema,

    temaActual() {
        return document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
    },

    alternarTema() {
        aplicarTema(this.temaActual() === 'dark' ? 'light' : 'dark');
    },

    /** Formatea un número como importe con el símbolo configurado. */
    money(valor, simbolo = 'Bs') {
        const n = Number(valor || 0);
        return `${n.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${simbolo}`;
    },

    montarSelectorPaciente,
    montarSelectoresPaciente,
    hacerTablasResponsivas,
});

document.addEventListener('DOMContentLoaded', () => {
    montarSelectoresPaciente();
    hacerTablasResponsivas();

    document.querySelectorAll('[data-os-theme-toggle]').forEach((boton) => {
        boton.addEventListener('click', (e) => {
            e.preventDefault();
            window.OdontoSuite.alternarTema();
        });
    });

    // Confirmación antes de enviar formularios destructivos.
    document.querySelectorAll('form[data-confirmar]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (!window.confirm(form.dataset.confirmar)) {
                e.preventDefault();
            }
        });
    });

    // Auto-cierre de las alertas de sesión.
    document.querySelectorAll('.alert[data-auto-cerrar]').forEach((alerta) => {
        setTimeout(() => alerta.classList.add('d-none'), 6000);
    });
});

/**
 * PWA: registra el service worker (public/sw.js) para que el sistema pueda
 * instalarse desde el navegador y muestre una página propia sin conexión.
 * Sólo en contextos seguros (https o localhost), que es donde el navegador
 * permite service workers.
 */
if ('serviceWorker' in navigator
    && (window.location.protocol === 'https:' || ['localhost', '127.0.0.1'].includes(window.location.hostname))) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
            /* sin service worker la aplicación funciona igual */
        });
    });
}
