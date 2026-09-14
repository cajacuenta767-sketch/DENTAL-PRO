import { expect, test } from '@playwright/test';
import { COLORES, entrarComoAdmin, relleno } from './ayuda.mjs';

test.describe('Odontograma en arcada', () => {
    test.beforeEach(async ({ page }) => {
        await entrarComoAdmin(page);
        await page.goto('/admin/pacientes/3/odontograma/nuevo');
        await page.waitForSelector('[data-arcada] svg');
        await page.click('[data-os-vista="arcada"]');
    });

    test('pinta caras y piezas completas desde la arcada y el panel', async ({ page }) => {
        await page.locator('[data-arcada] [data-pieza="16"] [data-cara="oclusal"]').click({ force: true });
        expect(await relleno(page, '[data-arcada] [data-pieza="16"] [data-cara="oclusal"]')).toBe(COLORES.caries);
        await expect(page.locator('[data-panel-numero]')).toHaveText('16');

        await page.keyboard.press('5');
        await page.locator('[data-detalle-cara="vestibular"]').click();
        expect(await relleno(page, '[data-arcada] [data-pieza="16"] [data-cara="vestibular"]')).toBe(COLORES.obturado);

        await page.locator('[data-arcada] [data-pieza="26"]').click({ force: true });
        await page.locator('[data-chip="corona"]').click();
        expect(await relleno(page, '[data-arcada] [data-pieza="26"] [data-cara="mesial"]')).toBe(COLORES.corona);
    });

    test('atajos, deshacer, copia y capas', async ({ page }) => {
        await page.keyboard.press('p');
        await page.keyboard.press('3');
        await page.locator('[data-arcada] [data-pieza="48"]').click({ force: true });
        await expect(page.locator('[data-arcada] [data-pieza="48"]')).toHaveAttribute('data-estado', 'extraccion');

        await page.click('[data-os-copiar-contralateral]');
        await expect(page.locator('[data-arcada] [data-pieza="38"]')).toHaveAttribute('data-estado', 'extraccion');

        await page.click('[data-os-deshacer]');
        await expect(page.locator('[data-arcada] [data-pieza="38"]')).toHaveAttribute('data-estado', 'sano');

        await page.keyboard.press('Escape');
        await expect(page.locator('[data-panel-numero]')).toHaveText('—');

        await page.click('[data-os-vista="cuadricula"]');
        expect(await relleno(page, '[data-os-vista-panel="cuadricula"] [data-pieza="48"] [data-cara="oclusal"]')).not.toBe(COLORES.blanco);
    });

    test('guarda y el listado muestra los hallazgos pintados', async ({ page }) => {
        await page.locator('[data-arcada] [data-pieza="11"] [data-cara="oclusal"]').click({ force: true });
        await page.locator('[data-panel-urgente]').check();
        await page.click('#form-odontograma button[type=submit]');
        await page.waitForURL('**/admin/pacientes/3/odontograma');
        await page.waitForSelector('[data-arcada][data-montada] svg');
        expect(await relleno(page, '[data-arcada][data-montada] [data-pieza="11"] [data-cara="oclusal"]')).toBe(COLORES.caries);
        await expect(page.getByText('Urgentes: piezas 11').first()).toBeVisible();
    });
});
