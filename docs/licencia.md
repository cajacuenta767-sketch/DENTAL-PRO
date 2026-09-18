# Licencia (CONTROL)

DENTAL-PRO se integra con **CONTROL**, el panel central de licencias, ventas y caja
de la agencia. Código de producto en CONTROL: `dental-pro`.

## Cómo funciona

1. La clínica recibe una clave `CTL-XXXX-XXXX-XXXX-XXXX`.
2. Al registrarla (pantalla `/licencia` o `php artisan licencia:activar`), DENTAL-PRO
   llama a `POST {CONTROL_URL}/api/v1/licencias/activar` con
   `{ clave, producto: 'dental-pro', huella, dominio, nombre_equipo, version }` y recibe
   un **token** `base64url(payload).base64url(firma)` firmado con Ed25519.
3. El token se guarda en `storage/app/control/licencia.json` y se verifica **en cada
   petición sin red** con `sodium_crypto_sign_verify_detached` y la clave pública de
   CONTROL (`CONTROL_CLAVE_PUBLICA`). El payload incluye `clave`, `producto`, `huella`,
   `plan`, `estado`, `etiqueta`, `vence_en`, `soporte_hasta`, `emitido_en` y `expira_en`
   (7 días). Un token de otra clave, otro producto u otro equipo no vale.
4. Un **latido** (`POST /api/v1/licencias/latido` con `{ clave, huella, version }`)
   renueva el token. Se dispara:
   - a diario desde el scheduler (`php artisan licencia:latido`);
   - desde las peticiones, solo cuando el token guardado expira en menos de
     `control.renovar_dias` (2) o el estado es `mora`, y como máximo una vez por hora
     (`control.latido_cada`, Cache);
   - manualmente con **Reactivar / verificar ahora** (que hace `activar`).
5. Sin conexión, el sistema sigue funcionando hasta `expira_en` del último token.

## Estados

| Estado en el token | Efecto |
|---|---|
| `activa` | Entra con normalidad. |
| `mora` | Vencida dentro del periodo de gracia: entra y muestra un aviso en cada pantalla. |
| `suspendida`, `vencida`, `revocada`, `pendiente_pago` | CONTROL rechaza el latido con `403 { ok:false, codigo, error, licencia }`; se borra el token, se guarda `ultimo_error` y el sistema queda bloqueado con ese motivo. |

Otros códigos locales: `sin_clave` (no hay clave registrada), `sin_activar` (hay clave
pero nunca se activó), `token_vencido` (el token expiró y no hubo red), `sin_conexion`,
`no_encontrada`, `producto_incorrecto`, `max_activaciones`, `no_activada` (los devuelve
CONTROL).

## Qué se bloquea

Con `CONTROL_ACTIVO=true` y licencia no válida, el middleware `VerificarLicencia`
(grupos `web` y `api`) bloquea las rutas de `control.rutas_protegidas`: el panel
(`admin.*`), el portal del paciente (`portal.*`), la reserva pública (`reservas.*`) y la
API (`api.*`, `api/*`).

- Petición web → redirección a `/licencia` con el motivo.
- Petición JSON o `api/*` → `402 { ok: false, error, codigo }`.

Siempre se pueden abrir (`control.rutas_libres`): `login`, `logout`, recuperación de
contraseña, registro, verificación de correo, `licencia.*`, `/`, `/up` y `/build/*`.
El webhook de pagos y los enlaces firmados de confirmación no están en la lista de
protegidas, así que siguen funcionando.

Con `CONTROL_ACTIVO=false` (valor por defecto; desarrollo y pruebas) el middleware no
hace nada.

## Configuración

| Variable | Uso |
|---|---|
| `CONTROL_ACTIVO` | `true` exige licencia. |
| `CONTROL_URL` | URL base de CONTROL. |
| `CONTROL_CLAVE_PUBLICA` | Clave pública Ed25519 en base64. Se obtiene una vez con `GET {CONTROL_URL}/api/v1/licencias/clave-publica` (`{ algoritmo: 'Ed25519', clave_publica }`). |
| `CONTROL_LICENCIA` | Clave de licencia. Opcional: si la clínica la registra desde `/licencia` queda en el archivo, que tiene prioridad. |
| `CONTROL_HUELLA` | Identificador del equipo/dominio. Vacío: en `localhost`/`127.0.0.1` se usa `substr(sha256(hostname), 0, 32)` (escritorio); en un servidor, el dominio de `APP_URL`. |
| `APP_VERSION` | Versión instalada (`config('app.version')`, por defecto `2.0.0`). CONTROL responde `version_actual` y `desactualizada` y la pantalla avisa. |

`config/control.php` además define `producto`, `archivo`, `rutas_libres`,
`rutas_protegidas`, `renovar_dias` y `latido_cada`.

### Archivo `storage/app/control/licencia.json`

```json
{
  "clave": "CTL-AB12-CD34-EF56-GH78",
  "token": "eyJ...firma",
  "info": { "estado": "activa", "vence_en": "...", "version_actual": "2.0.0", "desactualizada": false },
  "ultimo_error": null,
  "guardado_en": "2026-09-14T10:00:00-04:00"
}
```

Lo escriben la pantalla `/licencia`, `licencia:activar` y cada latido. Al cambiar de
clave se descarta el token anterior. Respáldalo con el resto de `storage/app`.

## Pantalla `/licencia`

Sigue la pantalla estándar de CONTROL (`sdk/pantalla-licencia`): estado con color y
motivo, clave, plan/etiqueta, vence, soporte hasta, "funciona sin internet hasta",
equipo (huella), versión instalada y aviso de versión nueva.

| Acción | Ruta | Quién |
|---|---|---|
| Ver | `GET /licencia` (`licencia.mostrar`) | Cualquiera, incluso bloqueado o sin sesión. |
| Ingresar o cambiar clave | `POST /licencia/clave` (`licencia.clave`) | Usuario autenticado con `ajustes.editar` o SUPER ADMINISTRADOR. Si aún no hay clave (primer arranque), cualquier usuario autenticado. |
| Reactivar / verificar ahora | `POST /licencia/reactivar` (`licencia.reactivar`) | Ídem. |
| Código de emergencia | `POST /licencia/emergencia` (`licencia.emergencia`) | Ídem. |

Las acciones tienen `throttle:10,1`. En Ajustes de la clínica hay una tarjeta
**Licencia del sistema** (estado, vence, botón) visible con `ajustes.editar`.

## Código de emergencia (72 h)

Si CONTROL está caído, la clínica no tiene internet o hay un pago en trámite, un
vendedor o admin genera desde CONTROL → Licencia → **Código de emergencia** indicando
la **huella** que aparece en `/licencia`. Es un token firmado con la misma clave privada,
con `emergencia: true`, `estado: 'activa'` y `expira_en` a 72 h, válido solo para esa
huella. Se pega en el campo de la pantalla y se guarda como token normal
(`aplicarCodigoEmergencia`), sin red. Un código de otro equipo, vencido o un token
normal se rechazan.

## Comandos

| Comando | Uso |
|---|---|
| `php artisan licencia:activar CTL-XXXX-XXXX-XXXX-XXXX` | Registra la clave y activa este equipo (instalador de escritorio, despliegues). Falla con formato inválido o si CONTROL rechaza. |
| `php artisan licencia:latido` | Latido diario (programado en `routes/console.php`). Muestra el resumen; devuelve error si la licencia no es válida. |

## Escritorio

La versión de escritorio define `CONTROL_HUELLA` con el identificador de la máquina
(hostname + id de máquina) y ejecuta `licencia:activar` en la instalación. Si no la
define y la app corre en `localhost`, se usa el hash del hostname.

## Código

| Archivo | Responsabilidad |
|---|---|
| `app/Services/Control/ControlLicencia.php` | SDK: `activar`, `latido`, `verificar`, `cargar`, `aplicarCodigoEmergencia`, `guardarClave`, `resumen`. HTTP con `Http::timeout(10)` (simulable con `Http::fake()`). |
| `app/Services/Control/Licencia.php` | Singleton (`AppServiceProvider`): `estado()` en caché por petición, decisión de latido, `verificarAhora`, `latido`, huella por defecto. |
| `app/Http/Middleware/VerificarLicencia.php` | Bloqueo por rutas; aviso en `mora`. |
| `app/Http/Controllers/LicenciaController.php` + `resources/views/licencia.blade.php` | Pantalla estándar. |
| `app/Console/Commands/LicenciaLatido.php`, `LicenciaActivar.php` | Comandos. |
| `tests/Feature/LicenciaTest.php` | Firma tokens con un par Ed25519 propio y simula CONTROL. |
