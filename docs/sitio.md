# Web de producto (`sitio/`)

`sitio/` es una web estática (HTML, CSS y JavaScript sin dependencias) para
presentar, vender y distribuir DENTAL-PRO. Se integra con el panel central de
ventas y licencias **CONTROL** a través de sus endpoints públicos y con GitHub
Releases para las descargas.

## Qué contiene

| Archivo | Función |
|---|---|
| `index.html` | Página única: módulos, capturas, cómo funciona, precios, compra, consulta de pedido, descargas, demo, preguntas frecuentes y contacto |
| `estilos.css` | Estilos propios (tipografía del sistema, paleta `#0d9488`, modo oscuro por `prefers-color-scheme`, responsive desde 360 px) |
| `app.js` | Catálogo y precios desde CONTROL, conversión de moneda, pedidos, consulta de pedido, referidos `?ref=`, releases de GitHub, galería y menú |
| `config.js` | **El único archivo que hay que editar** para apuntar la web a tu infraestructura |
| `img/` | Capturas reales del sistema (JPEG a 1440 px, menos de 160 KB cada una) |
| `favicon.svg`, `robots.txt`, `sitemap.xml`, `.nojekyll` | SEO y despliegue |

## Configurar `config.js`

```js
window.DENTAL_PRO_CONFIG = {
  CONTROL_URL: 'https://control.tuagencia.com',   // URL pública de CONTROL
  DEMO_URL: 'https://demo.dental-pro.app',        // instalación de demostración
  RELEASES_URL: 'https://github.com/cajacuenta767-sketch/DENTAL-PRO/releases',
  GITHUB_REPO: 'cajacuenta767-sketch/DENTAL-PRO', // para leer versión y tamaño de los archivos
  WHATSAPP: '59170000000',                        // sin "+" ni espacios
  CORREO: 'ventas@tuagencia.com',
  AGENCIA: 'Tu Agencia',
  SITIO_URL: 'https://cajacuenta767-sketch.github.io/DENTAL-PRO/',
  CUENTAS_DEMO: [...],                            // usuarios del seeder
  PRECIOS_RESPALDO: { ... },                      // tabla que se muestra si CONTROL no responde
};
```

- **`CONTROL_URL`**: de ahí se leen `GET /api/v1/publico/catalogo` (planes, precios,
  `tipos_cambio`, `pasarelas`), se envía `POST /api/v1/publico/pedidos`, se consulta
  `GET /api/v1/publico/pedidos/:numero?email=` y `GET /api/v1/publico/vendedor/:codigo`.
  El enlace "Portal de clientes" apunta a `CONTROL_URL/portal`.
  **CONTROL debe permitir el origen de la web en CORS**: añade la URL del sitio a la
  variable `ORIGENES` del `backend/.env` de CONTROL (separada por comas), por ejemplo
  `ORIGENES=https://control.tuagencia.com,https://cajacuenta767-sketch.github.io`.
- **`PRECIOS_RESPALDO`**: si el navegador no puede llegar a CONTROL (sin red, CORS,
  servidor caído) la sección de precios muestra esta tabla con un aviso y el formulario
  ofrece enviar el pedido por WhatsApp o correo. Mantenla igual que la lista de precios
  del catálogo (nivel 4: 39 / 349 / 790 USD, sede adicional 319, mantenimiento 158).
- **Descargas**: los botones apuntan a `RELEASES_URL/latest/download/DENTAL-PRO-Setup.exe`
  y `.../DENTAL-PRO.apk`. Como los artefactos reales se llaman
  `DENTAL-PRO-Setup-<versión>.exe` y `DENTAL-PRO-<versión>.apk`, la página consulta
  `https://api.github.com/repos/<GITHUB_REPO>/releases/latest` y reemplaza los enlaces por
  las URL exactas, mostrando versión, tamaño y fecha. Si la API no responde, los botones
  llevan a la página de releases. Para que el enlace fijo funcione sin la API, publica
  también en cada release una copia de los archivos con el nombre fijo.
- **Referidos**: `https://tu-sitio/?ref=CODIGO` muestra el nombre del vendedor y envía
  `ref` con el pedido para que CONTROL le atribuya la venta y la comisión.
- Si cambias `SITIO_URL`, actualiza también `robots.txt`, `sitemap.xml` y las etiquetas
  `canonical`/Open Graph de `index.html`.

## Publicar en GitHub Pages

El workflow `.github/workflows/sitio.yml` sube la carpeta `sitio/` a GitHub Pages con
`actions/configure-pages@v5`, `actions/upload-pages-artifact@v3` y
`actions/deploy-pages@v4`. Se ejecuta a mano (`workflow_dispatch`) y en cada `push` a la
rama por defecto (`main`/`master`) o a `claude/pensive-cannon-pt8c62` que toque `sitio/**`.

**Lo que debe hacer el dueño del repositorio una sola vez:**

1. En GitHub: **Settings → Pages → Build and deployment → Source: GitHub Actions**.
   Sin este paso el despliegue falla con "Pages not enabled".
2. Lanzar el workflow desde **Actions → Sitio web (GitHub Pages) → Run workflow**
   (o hacer un push que toque `sitio/`).
3. La web queda en `https://<usuario>.github.io/DENTAL-PRO/`. Para un dominio propio,
   añade el archivo `sitio/CNAME` con el dominio y configura el DNS según la guía de
   GitHub Pages; actualiza después `SITIO_URL`, `robots.txt` y `sitemap.xml`.

## Servirla junto a CONTROL (Caddy o Nginx)

La carpeta es estática: puede servirse desde cualquier servidor web, por ejemplo en el
mismo host de CONTROL.

Caddy (`Caddyfile`):

```caddy
dental-pro.tuagencia.com {
    root * /srv/dental-pro/sitio
    file_server
    encode gzip
}
```

Nginx:

```nginx
server {
    listen 443 ssl http2;
    server_name dental-pro.tuagencia.com;
    root /srv/dental-pro/sitio;
    index index.html;
    location / { try_files $uri $uri/ =404; }
    location ~* \.(jpg|png|webp|svg|css|js)$ { expires 7d; add_header Cache-Control "public"; }
}
```

Recuerda añadir `https://dental-pro.tuagencia.com` a `ORIGENES` en CONTROL.

## Actualizar las capturas

Las imágenes de `sitio/img/` se toman con Playwright sobre una instalación con los datos
de demostración y la sesión de administrador guardada en `test-results/sesion-admin.json`
(la genera `tests/Browser/preparar.mjs`). Páginas capturadas: `/admin/home`,
`/admin/agenda/semana?fecha=2026-07-20`, `/admin/pacientes/3/odontograma/nuevo` (vistas
arcada y 3D), `/admin/pacientes/3/periodontograma/nuevo`, `/admin/estudios`,
`/admin/caja/arqueo`, `/admin/presupuestos`, `/admin/reportes` y `/admin/ajustes`, a
1440 px de ancho, en JPEG con calidad 80. Cualquier script equivalente sirve; mantén cada
archivo por debajo de 400 KB y actualiza el `alt` de `index.html` si cambias el contenido.

## Comprobaciones

```bash
npx html-validate sitio/index.html        # HTML válido
python3 -m http.server 8766 --directory sitio   # abrir http://127.0.0.1:8766
```

Con el servidor local y sin acceso a CONTROL la sección de precios debe mostrar la tabla
de respaldo con el aviso "Precios de lista de referencia", el formulario debe marcar los
campos obligatorios y no debe haber errores en la consola del navegador (aparte de los
fallos de red hacia CONTROL o la API de GitHub).
