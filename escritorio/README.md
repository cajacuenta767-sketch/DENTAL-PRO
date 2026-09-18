# DENTAL-PRO · aplicación de escritorio (Windows)

Lanzador Electron que empaqueta la aplicación Laravel junto con PHP 8.4 y
PostgreSQL 16 para instalarla en un PC de la clínica sin dependencias externas.
La documentación de usuario está en [`../docs/escritorio.md`](../docs/escritorio.md).

## Estructura

| Archivo | Para qué |
|---|---|
| `main.js` | Proceso principal: arranca PostgreSQL y `php artisan serve`, migra, abre la ventana, bandeja, menú, cierre limpio. |
| `preload.js` / `ventana-carga.html` | Ventana de carga (progreso y errores legibles). La ventana principal no tiene acceso a Node. |
| `lib/` | Lógica pura y probada: puertos libres, `.env`, huella CONTROL, versiones, copia de archivos, espera HTTP. |
| `test/*.test.mjs` | Pruebas con `node --test` (`npm test`). |
| `config.json` | Valores por defecto (URL y clave pública de CONTROL, zona horaria, moneda). |
| `preparar-app.mjs` | Copia el proyecto Laravel a `recursos/app` (sin tests, node_modules, datos privados) y escribe `version.txt`. |
| `preparar-runtime.ps1` | Descarga PHP NTS x64 y PostgreSQL (binarios EDB) a `recursos/runtime` y escribe `php.ini`. |
| `electron-builder.yml` / `instalador.nsh` | Instalador NSIS en español, por usuario, con acceso directo; instala VC++ Redistributable si falta. |
| `herramientas/generar-iconos.mjs` | Regenera `icono.ico`, `icono.png` e `icono-32.png` desde `public/favicon.svg`. |

`recursos/`, `dist/` y `node_modules/` están ignorados en git.

## Generar el instalador

En GitHub Actions (`.github/workflows/escritorio.yml`) el flujo es automático.
A mano, en Windows con PHP 8.4, Composer, Node 22 y PowerShell 7:

```powershell
composer install --no-dev --optimize-autoloader
npm ci; npm run build
node escritorio/preparar-app.mjs
pwsh escritorio/preparar-runtime.ps1
cd escritorio
npm ci
npx electron-builder --win nsis --publish never
# → escritorio/dist/DENTAL-PRO-Setup-2.0.0.exe
```

## Probar el lanzador sin empaquetar

```powershell
node escritorio/preparar-app.mjs
pwsh escritorio/preparar-runtime.ps1
cd escritorio; npm ci; npm start
```

En desarrollo (`app.isPackaged === false`) los recursos se leen de `escritorio/recursos/`.
En Linux/macOS, si no existen `recursos/runtime/php/php.exe` ni `pgsql/bin`, el
lanzador usa `php`, `initdb` y `pg_ctl` del PATH (útil para depurar la lógica).

## Datos en el equipo del cliente

`%LOCALAPPDATA%\DENTAL-PRO\`

```
app\            copia de la aplicación Laravel (se reemplaza al cambiar de versión)
app\.env        configuración real (clave, contraseña de la base, licencia)
.env            copia espejo del anterior
archivos\       storage de Laravel: archivos privados, sesiones, caché, licencia
datos\pgsql\    clúster PostgreSQL (initdb con scram-sha-256 y contraseña aleatoria)
logs\           escritorio.log, postgres.log, servidor-php.log, tareas.log
electron\       perfil de Chromium
config.json     (opcional) sobrescribe valores de config.json del paquete
```
