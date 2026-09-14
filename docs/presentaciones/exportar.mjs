// Exporta las piezas de index.html como JPG de 1080 px en los tres formatos.
// Uso:  npm i -D playwright && npx playwright install chromium && node docs/presentaciones/exportar.mjs
//       Parámetros opcionales: --marca "Mi Clínica" --web "WWW.MICLINICA.COM" --wa "+57 300 000 0000"
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const args = process.argv.slice(2);
const opt = (k) => { const i = args.indexOf(`--${k}`); return i >= 0 ? args[i + 1] : null; };
const query = new URLSearchParams({ export: '1' });
for (const k of ['marca', 'web', 'wa']) if (opt(k)) query.set(k, opt(k));

const FORMATOS = { square: 'post-1x1', portrait: 'feed-4x5', story: 'historia-9x16' };
const slug = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1080, height: 1920 } });
for (const [fmt, carpeta] of Object.entries(FORMATOS)) {
  const out = path.join(dir, 'img', carpeta);
  fs.mkdirSync(out, { recursive: true });
  query.set('fmt', fmt);
  await page.goto(`${pathToFileURL(path.join(dir, 'index.html'))}?${query}`, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);
  const piezas = await page.$$('.post');
  for (let i = 0; i < piezas.length; i++) {
    const nombre = await piezas[i].$eval('.chip', (e) => e.textContent.replace('Módulo · ', ''));
    const archivo = path.join(out, `${String(i + 1).padStart(2, '0')}-${slug(nombre)}.jpg`);
    await piezas[i].screenshot({ path: archivo, type: 'jpeg', quality: 90 });
    console.log(archivo);
  }
}
await browser.close();
