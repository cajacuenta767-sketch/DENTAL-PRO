# DENTAL-PRO de escritorio (Windows)

`DENTAL-PRO-Setup-<versión>.exe` instala la aplicación completa en un PC de la
clínica. No hace falta instalar PHP, PostgreSQL ni ningún servidor: el
instalador lleva dentro PHP 8.4 y PostgreSQL 16 y los arranca solo cuando abres
DENTAL-PRO.

## Requisitos

- Windows 10 u 11 de 64 bits (x64).
- Unos 700 MB libres en disco para la instalación, más el espacio de tus datos.
- Cuenta de usuario normal (no hace falta ser administrador). Si falta el
  *Microsoft Visual C++ Redistributable 2015-2022 (x64)*, el instalador lo
  instala y Windows pedirá permiso una vez.
- Internet solo para activar la licencia y para el latido periódico con CONTROL
  (la aplicación sigue funcionando sin conexión hasta que expira el token,
  normalmente 7 días).

## Qué instala y dónde

| Ubicación | Contenido |
|---|---|
| `%LOCALAPPDATA%\Programs\DENTAL-PRO\` (o la carpeta que elijas) | El programa: lanzador, `resources\runtime` (PHP y PostgreSQL) y `resources\app` (la aplicación). Se sustituye por completo en cada actualización. |
| `%LOCALAPPDATA%\DENTAL-PRO\` | **Tus datos.** Nunca los toca el instalador ni el desinstalador (este último pregunta si quieres borrarlos). |

Dentro de `%LOCALAPPDATA%\DENTAL-PRO\`:

```
app\             copia de trabajo de la aplicación (se reemplaza al cambiar de versión)
app\.env         configuración: clave de la aplicación, contraseña de la base, licencia
.env             copia espejo del anterior, por si app\.env se pierde
archivos\        archivos privados (estudios, fotos, firmas, documentos), sesiones,
                 caché, licencia (archivos\app\control\licencia.json) y respaldos
                 (archivos\app\respaldos\*.zip)
datos\pgsql\     base de datos PostgreSQL (clúster privado de DENTAL-PRO)
logs\            escritorio.log (lanzador), postgres.log, servidor-php.log, tareas.log
electron\        caché del navegador integrado
config.json      opcional: sobrescribe la URL / clave pública de CONTROL, zona horaria…
```

Para abrir esa carpeta desde la aplicación: menú **Aplicación → Abrir carpeta de
datos**. Los registros: **Aplicación → Ver registros**.

## Primer arranque

1. Abre DENTAL-PRO desde el acceso directo. Verás una ventana de carga con el
   progreso: crea la base de datos (`initdb`, con contraseña aleatoria guardada en
   `app\.env` y autenticación `scram-sha-256`; PostgreSQL solo escucha en
   `127.0.0.1`), copia la aplicación, genera `APP_KEY`, ejecuta las migraciones y
   siembra los datos mínimos (`ProduccionSeeder`: ajustes neutros, una sede, roles,
   catálogo de especialidades y tratamientos). Tarda uno o dos minutos solo la
   primera vez.
2. Entra con **admin@admin.com / admin123**. El sistema te obliga a cambiar la
   contraseña.
3. Completa **Configuración** (nombre de la clínica, moneda, sede) y **Usuarios**.

En cada arranque posterior el lanzador levanta PostgreSQL (`pg_ctl start`),
aplica migraciones pendientes (`migrate --force`, idempotente), arranca el
servidor PHP en un puerto libre a partir de 8181 y abre la ventana maximizada.
Cada minuto ejecuta `php artisan schedule:run` (recordatorios de citas,
respaldo diario a las 02:00, latido de licencia).

Al cerrar la ventana, DENTAL-PRO **sigue en la bandeja del sistema** (icono
junto al reloj) para que el servidor y las tareas programadas continúen. Para
apagarlo del todo: clic derecho en el icono de la bandeja → **Salir** (o menú
**Aplicación → Salir**). Al salir se detiene PostgreSQL de forma ordenada
(`pg_ctl stop -m fast`). Si abres el acceso directo con la aplicación ya en
marcha, simplemente se trae la ventana al frente.

## Licencia (CONTROL)

La instalación se ata a este equipo mediante una huella
`sha256(nombre del equipo + MachineGuid de Windows)` recortada a 32 caracteres.
El lanzador escribe en `app\.env` las variables `CONTROL_ACTIVO=true`,
`CONTROL_URL`, `CONTROL_CLAVE_PUBLICA`, `CONTROL_HUELLA` y `APP_VERSION`; los
valores de URL y clave pública salen de `config.json` del paquete y pueden
sobrescribirse creando `%LOCALAPPDATA%\DENTAL-PRO\config.json`:

```json
{ "controlUrl": "https://control.tuagencia.com", "controlClavePublica": "…base64…" }
```

Para activar:

- **Desde la aplicación:** menú **Aplicación → Licencia** (pantalla `/licencia`),
  pega la clave `CTL-XXXX-XXXX-XXXX-XXXX` y pulsa *Activar*. Ahí mismo se ve el
  estado, el vencimiento y se puede introducir un *código de emergencia* de 72 h
  si no hay internet.
- **Por consola** (útil para soporte): abre PowerShell en
  `%LOCALAPPDATA%\DENTAL-PRO\app` y ejecuta

  ```powershell
  $env:LARAVEL_STORAGE_PATH = "$env:LOCALAPPDATA\DENTAL-PRO\archivos"
  & "$env:LOCALAPPDATA\Programs\DENTAL-PRO\resources\runtime\php\php.exe" artisan licencia:activar CTL-XXXX-XXXX-XXXX-XXXX
  ```

Si cambias de PC (o se reinstala Windows y cambia el MachineGuid), la huella
cambia: pide en CONTROL un *reset* de la licencia y vuelve a activarla.

## Respaldos y restauración

- **Automático:** cada día a las 02:00 (si el equipo está encendido y DENTAL-PRO
  abierto o en la bandeja) se crea un ZIP con el volcado de PostgreSQL
  (`pg_dump -Fc`) y los archivos privados en `archivos\app\respaldos\`. Se
  conservan los últimos 14 (`RESPALDOS_CONSERVAR` en `app\.env`).
- **Manual:** menú **Aplicación → Respaldos** (`/admin/respaldos`): crear,
  descargar o eliminar respaldos. Copia el ZIP a un disco externo o a la nube;
  el respaldo en el mismo PC no protege ante un fallo del disco.
- **Restaurar en un equipo nuevo:** instala DENTAL-PRO, ábrelo una vez (crea la
  base vacía) y desde **Respaldos** usa la opción de restaurar el ZIP. Si tu
  versión no ofrece restauración desde la pantalla, hazlo por consola:

  ```powershell
  $bin = "$env:LOCALAPPDATA\Programs\DENTAL-PRO\resources\runtime\pgsql\bin"
  # DB_PORT y DB_PASSWORD están en %LOCALAPPDATA%\DENTAL-PRO\app\.env
  $env:PGPASSWORD = "<DB_PASSWORD>"
  & "$bin\pg_restore.exe" -h 127.0.0.1 -p <DB_PORT> -U postgres -d odontosuite --clean --if-exists <base.dump>
  ```

  y copia la carpeta `archivos` del ZIP sobre `%LOCALAPPDATA%\DENTAL-PRO\archivos\app\private`.
- **Copia completa "en frío":** con DENTAL-PRO cerrado (Salir), copiar toda la
  carpeta `%LOCALAPPDATA%\DENTAL-PRO` (sin `electron\`) es un respaldo íntegro.
  Restaurarla en otro equipo con la misma versión instalada recupera todo; solo la
  licencia habrá que reactivarla.

## Actualizar

Descarga el nuevo `DENTAL-PRO-Setup-<versión>.exe` y ejecútalo **con
DENTAL-PRO cerrado** (bandeja → Salir). Se instala encima; la base de datos, los
archivos, `app\.env` y la licencia se conservan. En el siguiente arranque el
lanzador detecta que cambió `version.txt`, sustituye la carpeta `app\`
(conservando `.env`), limpia cachés y aplica las migraciones nuevas. Los
respaldos se conservan en `archivos\app\respaldos`.

Si una actualización falla, la ventana de carga muestra el error y las últimas
líneas de los registros, con botones para reintentar, ver los registros o abrir
la carpeta de datos. Reinstalar la versión anterior encima vuelve a dejar su
código (las migraciones no se deshacen: restaura un respaldo si hace falta).

## Desinstalar

*Configuración → Aplicaciones → DENTAL-PRO → Desinstalar*. El desinstalador
elimina el programa y pregunta si además quieres borrar los datos
(`%LOCALAPPDATA%\DENTAL-PRO`). Contesta **No** si piensas reinstalar.

## Problemas frecuentes

| Síntoma | Qué hacer |
|---|---|
| "terminó con código 3221225781" / falta una DLL | Instala *Microsoft Visual C++ Redistributable 2015-2022 x64* (`resources\runtime\vc_redist.x64.exe`) y vuelve a abrir. |
| PostgreSQL no arranca | Mira `logs\postgres.log`. Un antivirus puede bloquear `postgres.exe`; añade `%LOCALAPPDATA%\DENTAL-PRO` y la carpeta del programa a las exclusiones. |
| "otro proceso php.exe" al actualizar | Cierra DENTAL-PRO desde la bandeja, comprueba en el Administrador de tareas que no quede `php.exe` ni `postgres.exe`, y vuelve a abrir. |
| No responde `/login` | Revisa `logs\servidor-php.log` y `archivos\logs\laravel-*.log`. |
| Perdí `app\.env` | Restáuralo desde `%LOCALAPPDATA%\DENTAL-PRO\.env` (copia espejo). Sin él no se conoce la contraseña de la base. |

## Generar el instalador

### Automático (GitHub Actions)

`.github/workflows/escritorio.yml` se ejecuta en `windows-latest` en cada push,
a mano (*Run workflow*) y al crear una etiqueta `v*`; en este último caso
adjunta el `.exe` a la release. Pasos: `composer install --no-dev`, `npm run
build`, pruebas del lanzador, `preparar-app.mjs`, `preparar-runtime.ps1`
(descarga PHP y PostgreSQL), prueba de humo (`php -v`, `php -m`, `initdb
--version`, `artisan --version`), un arranque real (initdb + migrate +
ProduccionSeeder + `artisan serve` → `/login` 200) y `electron-builder --win nsis`.
El artefacto `DENTAL-PRO-Setup` queda descargable en la ejecución.

### Manual (en Windows)

Requisitos: PHP 8.4, Composer 2, Node 22 y PowerShell 7 (`pwsh`).

```powershell
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci; npm run build
node escritorio/preparar-app.mjs            # → escritorio/recursos/app + version.txt
pwsh escritorio/preparar-runtime.ps1        # → escritorio/recursos/runtime/{php,pgsql}
cd escritorio
npm ci
npx electron-builder --win nsis --publish never
# → escritorio/dist/DENTAL-PRO-Setup-2.0.0.exe
```

La versión del instalador es la de `escritorio/package.json`. Para actualizar
PostgreSQL: `pwsh escritorio/preparar-runtime.ps1 -VersionPostgres 16.<x>-1`
(lista en enterprisedb.com → *Download PostgreSQL binaries*); PHP toma siempre
la última 8.4 NTS x64 publicada en windows.php.net. Los iconos se regeneran con
`node escritorio/herramientas/generar-iconos.mjs` a partir de `public/favicon.svg`.

El instalador no está firmado: Windows SmartScreen mostrará un aviso ("Más
información → Ejecutar de todas formas") hasta que se firme con un certificado
de código (electron-builder admite `CSC_LINK`/`CSC_KEY_PASSWORD` o firma con
Azure Trusted Signing).
