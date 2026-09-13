import './bootstrap';

import '@tabler/core/dist/js/tabler.min.js';
import ApexCharts from 'apexcharts';

window.ApexCharts = ApexCharts;

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

window.OdontoSuite = {
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
};

document.addEventListener('DOMContentLoaded', () => {
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
