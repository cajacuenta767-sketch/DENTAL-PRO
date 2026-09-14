import { defineConfig } from '@playwright/test';

/**
 * Pruebas de interfaz. Necesitan la aplicación levantada con la base de
 * demostración: `php artisan migrate:fresh --seed` y `php artisan serve`.
 * En CI el workflow lo hace; en local: `npm run test:ui`.
 */
export default defineConfig({
    testDir: './tests/Browser',
    timeout: 60_000,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL: process.env.APP_URL_UI ?? 'http://127.0.0.1:8765',
        locale: 'es-ES',
        viewport: { width: 1400, height: 1000 },
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
        launchOptions: process.env.PW_CHROMIUM_PATH ? { executablePath: process.env.PW_CHROMIUM_PATH } : {},
    },
    projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
});
