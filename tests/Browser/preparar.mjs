import { chromium } from '@playwright/test';
import { iniciarSesionAdmin, RUTA_SESION } from './ayuda.mjs';

/**
 * Inicia sesión una sola vez y guarda las cookies para todas las pruebas.
 * Así el límite de intentos de inicio de sesión (5 por minuto) no se agota
 * cuando la suite crece.
 */
export default async function preparar(config) {
    const { baseURL, launchOptions } = config.projects[0].use;
    const navegador = await chromium.launch(launchOptions ?? {});
    const contexto = await navegador.newContext({ baseURL });
    const page = await contexto.newPage();

    await iniciarSesionAdmin(page);
    await contexto.storageState({ path: RUTA_SESION });
    await navegador.close();
}
