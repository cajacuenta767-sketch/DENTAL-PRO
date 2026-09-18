import { expect, test } from '@playwright/test';
import { entrarComoAdmin } from './ayuda.mjs';

const PAGINAS = [
    '/admin/home', '/admin/pacientes', '/admin/citas', '/admin/agenda', '/admin/pagos', '/admin/presupuestos',
    '/admin/facturacion', '/admin/inventario', '/admin/reportes', '/admin/auditoria', '/admin/lista-espera',
    '/admin/estudios', '/admin/documentos', '/admin/roles', '/admin/usuarios', '/admin/ajustes',
    '/admin/agenda/semana', '/admin/agenda/mes', '/admin/caja', '/admin/sucursales', '/admin/respaldos', '/perfil',
    '/admin/pacientes/3/periodontograma', '/admin/pacientes/3/periodontograma/nuevo', '/admin/caja/arqueo',
    '/admin/estudios/nuevo', '/admin/pacientes/3/panoramicas',
];

test.describe('Panel administrativo', () => {
    test('todas las pantallas principales cargan sin errores de consola', async ({ page }) => {
        const errores = [];
        page.on('pageerror', (e) => errores.push(e.message));

        await entrarComoAdmin(page);

        for (const ruta of PAGINAS) {
            const respuesta = await page.goto(ruta);
            expect(respuesta?.status(), ruta).toBe(200);
        }

        expect(errores).toEqual([]);
    });

    test('la galería de imagenología abre el visor al pulsar una tarjeta', async ({ page }) => {
        await entrarComoAdmin(page);
        await page.goto('/admin/estudios');
        const tarjeta = page.locator('[data-estudio][data-estudio-visualizable="1"]').first();
        await expect(tarjeta).toBeVisible();
        await tarjeta.locator('.card-body').click();
        await expect(page.locator('#visor-estudio')).toHaveClass(/show/);
        await expect(page.locator('#visor-estudio [data-visor-lienzo]')).toBeVisible();
        await expect(page.locator('#visor-estudio [data-anot-herramientas]')).toBeVisible();
    });

    test.describe('sin sesión', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('el registro público lleva al portal y no al panel', async ({ page }) => {
        await page.goto('/registro');
        const correo = `ui-${Date.now()}@pruebas.test`;
        await page.fill('input[name=nombre]', 'Paciente UI');
        await page.fill('input[name=email]', correo);
        await page.fill('input[name=password]', 'Portal2026x');
        await page.fill('input[name=password_confirmation]', 'Portal2026x');
        await page.click('form button[type=submit]');
        await page.waitForURL(/verificar-email|portal/);

        const admin = await page.goto('/admin/home');
        expect(admin?.status()).toBe(403);
    });
    });
});
