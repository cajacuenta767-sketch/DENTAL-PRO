/** Utilidades compartidas por las pruebas de interfaz. */

export const CUENTAS = {
    admin: { email: 'admin@admin.com', password: process.env.UI_ADMIN_PASSWORD ?? 'ClaveNueva2026' },
};

/** Archivo con las cookies de la sesión de administrador (lo crea preparar.mjs). */
export const RUTA_SESION = 'test-results/sesion-admin.json';

/**
 * Inicia sesión como administrador rellenando el formulario. Las cuentas de
 * demostración piden definir una contraseña nueva en el primer acceso: se
 * resuelve aquí. Solo se usa en la preparación global; las pruebas reciben
 * la sesión ya iniciada mediante `storageState`.
 */
export async function iniciarSesionAdmin(page) {
    await page.goto('/login');
    await page.fill('#email', CUENTAS.admin.email);
    await page.fill('#password', CUENTAS.admin.password);
    await Promise.all([page.waitForLoadState('networkidle'), page.click('button[type=submit]')]);

    // Primera vez: la clave temporal admin123 exige cambio.
    if (page.url().includes('/login')) {
        await page.fill('#email', CUENTAS.admin.email);
        await page.fill('#password', 'admin123');
        await page.click('button[type=submit]');
        await page.waitForURL('**/password/obligatoria');
        await page.fill('input[name=password_actual]', 'admin123');
        await page.fill('input[name=password]', CUENTAS.admin.password);
        await page.fill('input[name=password_confirmation]', CUENTAS.admin.password);
        await page.click('form button[type=submit]');
    }

    await page.waitForURL('**/admin/home');
}

/** Comprueba que la sesión compartida sigue válida entrando al panel. */
export async function entrarComoAdmin(page) {
    await page.goto('/admin/home');
    await page.waitForURL('**/admin/home');
}

/** Color calculado de una cara del odontograma, esperando la transición CSS. */
export async function relleno(page, selector) {
    await page.waitForTimeout(250);

    return page.locator(selector).first().evaluate((e) => getComputedStyle(e).fill);
}

export const COLORES = {
    caries: 'rgb(214, 57, 57)',
    obturado: 'rgb(32, 107, 196)',
    corona: 'rgb(112, 72, 232)',
    blanco: 'rgb(255, 255, 255)',
};
