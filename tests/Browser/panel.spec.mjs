import { expect, test } from '@playwright/test';
import { entrarComoAdmin } from './ayuda.mjs';

const PAGINAS = [
    '/admin/home', '/admin/pacientes', '/admin/citas', '/admin/agenda', '/admin/pagos', '/admin/presupuestos',
    '/admin/facturacion', '/admin/inventario', '/admin/reportes', '/admin/auditoria', '/admin/lista-espera',
    '/admin/estudios', '/admin/documentos', '/admin/roles', '/admin/usuarios', '/admin/ajustes',
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
