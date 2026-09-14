# OdontoSuite · Sistema de Gestión Odontológica

Sistema completo para clínicas dentales construido con **Laravel 12**, **Blade**,
**Tabler UI** y **PostgreSQL**. Incluye sitio público, reservas en línea con QR,
portal del paciente, panel administrativo con roles y permisos, agenda de citas
con duración por tratamiento, lista de espera, historia clínica digital,
odontograma interactivo por capas, imagenología en almacenamiento privado,
recetas, certificados y consentimientos firmados, presupuestos, inventario, caja,
facturación electrónica con notas de crédito, recordatorios automáticos, centro
de reportes con exportación a PDF y CSV, auditoría de cambios y doble factor.

---

## Módulos

| Módulo | Qué resuelve |
|---|---|
| **Home** | Panel con 8 indicadores, evolución de citas a 6 meses y reparto por estado |
| **Agenda del día** | Saludo, franja de indicadores y línea de tiempo de turnos; un doctor solo ve la suya salvo que tenga `agenda.todos` |
| **Lista de espera** | Pacientes que quieren un turno antes; prioridad, preferencia de turno y agendado en un clic |
| **Ajustes** | Datos de la clínica, divisa, logotipo, intervalo de cita y recordatorios |
| **Roles** | 5 roles predefinidos y 82 permisos por acción, editables desde la interfaz |
| **Usuarios** | Altas, estado activo/inactivo y asignación de roles |
| **Pacientes** | Ficha completa: antecedentes, alergias, contacto de emergencia y saldo |
| **Especialidades** | Catálogo con color identificador |
| **Tratamientos** | Catálogo con precio y duración, base de la agenda y la caja |
| **Doctores** | Ficha profesional, colegiatura y enlace con su usuario del sistema |
| **Horarios** | Disponibilidad semanal por turno, con detección de solapamientos |
| **Citas** | Token de confirmación, 5 estados, filtros, correo automático y enlace de WhatsApp; cada tratamiento bloquea su duración real |
| **Mi Agenda** | Vista diaria de cupos libres y ocupados por doctor |
| **Historia Clínica** | Registro por consulta con diagnóstico, tratamiento y receta en PDF |
| **Odontograma** | Mapa interactivo por pieza y cara (FDI), adulto e infantil |
| **Caja y Pagos** | Recibos con detalle, 4 métodos de cobro, saldos, anulación y PDF |
| **Reportes** | 4 secciones (financiero, productividad, padrón, rentabilidad) con PDF y CSV |
| **Aseguradoras** | Obras sociales y convenios con cobertura y tope anual |
| **Imagenología** | Radiografías, fotos clínicas, informes y resultados (imagen, PDF, Word/Excel, DICOM), carga múltiple, visor con zoom, anotaciones, comparación y descarga del original |
| **Recetas y Certificados** | 6 tipos de documento con folio correlativo, vigencia, PDF y firma del paciente en pantalla para consentimientos |
| **Presupuestos** | Plan de tratamiento por pieza, flujo evaluación → ejecución, cobertura y cobro |
| **Inventario** | Insumos con kardex, entradas, salidas, mermas, valorización y alertas |
| **Facturación** | Documentos tributarios electrónicos con serie, correlativo, IVA; anular emite la nota de crédito |
| **Turnos online** | Página pública de reserva con enlace y QR descargable |
| **Búsqueda global** | Un solo buscador sobre pacientes, citas, doctores, recibos y presupuestos |
| **Auditoría** | Quién creó, cambió o eliminó cada registro, con el antes y el después; ingresos y accesos fallidos |
| **Portal del paciente** | Cuenta propia para ver y cancelar citas, descargar recetas, presupuestos y recibos |

---

## Requisitos

- PHP **8.4+** con las extensiones `pdo_pgsql`, `mbstring`, `gd`, `zip` e `intl`
- Composer 2
- PostgreSQL **14+**
- Node.js 20+ y npm (solo para recompilar los estilos)

---

## Instalación

```bash
git clone <url-del-repositorio> odontosuite
cd odontosuite

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Crea la base de datos y ajusta las credenciales en `.env`:

```bash
createdb odontosuite
```

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=odontosuite
DB_USERNAME=postgres
DB_PASSWORD=tu_password
```

Migra, siembra los datos de demostración y compila:

```bash
php artisan migrate --seed
php artisan storage:link
npm run build

php artisan serve
```

Abre <http://localhost:8000>.

### Cuentas de demostración

| Rol | Correo | Contraseña |
|---|---|---|
| Super Administrador | `admin@admin.com` | `admin123` |
| Administrador | `admin@clinica.com` | `admin123` |
| Recepción | `secretaria@clinica.com` | `recepcion123` |
| Doctor | `sofia.arancibia@clinica.com` | `doctor123` |

> Son contraseñas temporales: el sistema exige definir una propia en el primer
> ingreso. Los usuarios creados desde el panel sin contraseña reciben una
> temporal que se muestra una sola vez.

El seeder genera 8 especialidades, 30 tratamientos, 10 doctores con 48 turnos,
50 pacientes, 200 citas, historias clínicas, odontogramas, recibos, 6 aseguradoras,
30 insumos con su kardex, estudios de imagen, recetas, presupuestos y documentos
fiscales repartidos en seis meses, para que el panel y los reportes tengan datos
reales desde el primer arranque. También deja activas las reservas en línea con su
enlace y QR listos en **Configuración → Turnos online**.

---

## Desarrollo

```bash
composer dev       # servidor, worker de colas, logs y Vite a la vez
npm run dev        # solo Vite en modo watch
php artisan test   # Suite de pruebas (Unit + Feature)
vendor/bin/pint    # Estilo de código
```

Los correos se encolan (`QUEUE_CONNECTION=database`), así que en desarrollo
necesitas el worker (`composer dev` lo levanta) y en producción un proceso
`php artisan queue:work` supervisado. Los recordatorios de cita salen del
programador: `php artisan schedule:work` en desarrollo o la entrada de cron
`* * * * * php artisan schedule:run` en producción. También puedes lanzarlos a
mano con `php artisan citas:recordar`.

Las pruebas corren contra una base aparte. Créala una sola vez:

```bash
createdb odontosuite_testing
```

### Docker

```bash
docker compose up --build
```

Levanta la aplicación, PostgreSQL, el worker de colas y el programador. El
contenedor `app` ejecuta las migraciones al arrancar.

### Integración continua

`.github/workflows/ci.yml` ejecuta Pint y la suite completa contra PostgreSQL 16
en cada push y pull request.

---

## Flujos que conectan los módulos

**Del odontograma al cobro.** Marcas los hallazgos en el odontograma, pulsas
*Generar presupuesto* y el plan se precarga con una línea por pieza afectada.
Al aprobarlo puedes marcar cada tratamiento como ejecutado; cuando cobras, el
recibo se arma solo con lo ya ejecutado y desde ahí se emite el documento fiscal.

**De la obra social al importe.** La aseguradora del paciente aplica su
porcentaje sobre el neto ya descontado y respeta el tope anual, así que el total
del presupuesto es lo que el paciente realmente paga.

**Del QR a la agenda.** El paciente escanea el QR de recepción, elige
especialidad, profesional y un cupo real (calculado con el horario del doctor y
las citas ya tomadas) y la reserva entra como cita **pendiente** para que la
confirmes desde el panel.

**Del kardex al stock.** Las existencias solo cambian por movimientos. Cada
entrada, salida, ajuste o merma deja su saldo resultante registrado, y el sistema
rechaza cualquier salida que dejaría el stock en negativo.

**De la lista de espera al turno.** Cuando no hay cupo, recepción anota al
paciente con su prioridad y preferencia de turno. Al liberarse una hora, el botón
*Agendar* abre el formulario de cita con todo precargado y cierra la entrada.

**Del consentimiento a la firma.** Un consentimiento informado se firma en la
pantalla (ratón o dedo) y la firma viaja al PDF con fecha y hora.

---

## Seguridad

- **Roles sin escalada.** Nadie concede permisos que no tiene ni asigna roles con
  más privilegios que los propios. El rol `PACIENTE` no tiene ningún permiso del
  panel: su casa es el portal.
- **Portal del paciente.** Quien se registra o entra con Google/GitHub queda como
  paciente y solo ve su propia información, previa verificación del correo.
- **Doble factor.** Cada usuario puede activar en su perfil un código de un solo
  uso enviado por correo en cada inicio de sesión.
- **Archivos privados.** Radiografías, fotos de pacientes y firmas viven en
  `storage/app/private` y se sirven únicamente a través de rutas autenticadas.
- **Límites de intentos** en login, registro, recuperación de contraseña, doble
  factor y reserva pública.
- **Auditoría.** Toda alta, cambio y baja de los modelos clínicos y financieros
  queda registrada con usuario, IP y el detalle de campos modificados. Los
  registros clínicos usan borrado lógico.
- **Integridad concurrente.** Los correlativos (recibos, presupuestos, folios y
  documentos fiscales) salen de una tabla de secuencias con bloqueo de fila, un
  cupo cancelado vuelve a ofrecerse gracias a un índice único parcial, y un
  recibo no admite dos facturas vigentes.

## Configuración adicional

### Correo

Las confirmaciones de cita y los comprobantes de pago se envían por correo.
En desarrollo `MAIL_MAILER=log` los deja en `storage/logs/laravel.log`. Para
enviarlos de verdad, configura tu SMTP en `.env`.

### Login con Google y GitHub

Los botones de acceso social aparecen en el login solo si las credenciales
están presentes:

```dotenv
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"

GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URI="${APP_URL}/auth/github/callback"
```

Quien entra por esta vía recibe el rol `PACIENTE`.

### Reservas en línea

En **Configuración → Turnos online** activas el interruptor y el sistema emite un
token que forma la URL pública y su código QR (descargable en SVG para imprimir).
Ahí defines la anticipación mínima y máxima y el mensaje que ve el paciente.
Regenerar el enlace invalida el anterior y su QR.

### Facturación electrónica

Los campos de serie, tasa de IVA y activación viven en los ajustes de la clínica.
El módulo emite documentos con número de control, código de generación y sello, y
guarda una copia de las líneas facturadas, de modo que editar el recibo después no
altera el documento ya emitido. La numeración es correlativa por tipo y serie.

> El módulo genera la representación gráfica y la numeración correlativa, pero
> **no transmite a ninguna administración tributaria**. Para operar en producción
> hay que conectar el firmado y el envío del organismo que corresponda a tu país.

### Recordatorios de cita

`horas_recordatorio` en **Ajustes** define con cuánta anticipación se avisa al
paciente. El comando `citas:recordar` corre cada hora desde el programador y
marca cada cita avisada para no repetir el envío. Se puede forzar la ventana y
el canal a mano: `php artisan citas:recordar --horas=48 --canal=whatsapp`.

### Recordatorios por WhatsApp y SMS

El canal del recordatorio se elige en **Ajustes → Recordatorios y portal**:
solo correo (por defecto), solo WhatsApp, solo SMS o correo y WhatsApp a la
vez. El correo y el mensaje corto incluyen un enlace firmado para que el
paciente confirme su asistencia con un clic. Los envíos los hace
`App\Services\MensajeriaService`, que normaliza el teléfono del paciente a
formato internacional (a los números de 8 dígitos les antepone
`MENSAJERIA_PREFIJO_PAIS`, `591` por defecto) y nunca lanza excepciones: cada
canal devuelve un resultado con éxito o error que queda en el log.

El proveedor se define en el servidor con `MENSAJERIA_PROVEEDOR`:

| Proveedor | Variables | Qué envía |
|-----------|-----------|-----------|
| `log` (por defecto) | ninguna | Escribe el mensaje en `storage/logs` y responde éxito. Es el modo de desarrollo y pruebas. |
| `twilio` | `TWILIO_SID`, `TWILIO_TOKEN`, `TWILIO_DESDE_SMS`, `TWILIO_DESDE_WHATSAPP` | SMS y WhatsApp por la API REST de Twilio. `TWILIO_DESDE_WHATSAPP` es el número habilitado en WhatsApp (por ejemplo el del *sandbox*, `+14155238886`). |
| `meta` | `META_WHATSAPP_TOKEN`, `META_WHATSAPP_PHONE_ID` | Solo WhatsApp, por la WhatsApp Cloud API de Meta (`graph.facebook.com/v20.0`). No envía SMS: si el canal es `sms` el resultado es "no soportado". |

Con WhatsApp Cloud API, los mensajes de texto libre solo llegan dentro de la
ventana de 24 horas posterior al último mensaje del paciente; fuera de ella
Meta exige plantillas aprobadas. Twilio no tiene esa limitación para SMS.

Desde la misma pantalla de Ajustes se puede enviar un **mensaje de prueba** a
un número para comprobar credenciales y prefijo antes de activar el canal.

### Copias de seguridad

El módulo **Respaldos** (grupo Configuración) genera un ZIP con el volcado de
PostgreSQL en formato *custom* (`base.dump`, `pg_dump -Fc`), la carpeta
`privado/` con los archivos de `storage/app/private` (estudios, fotografías y
firmas) y un `manifiesto.json` (fecha, versión y tablas). Los archivos se
guardan en `storage/app/respaldos` (disco `respaldos`) con el nombre
`respaldo-AAAAMMDD-HHMMSS.zip`.

- **Automático**: el programador ejecuta `sistema:respaldar` todos los días a
  las 02:00 (`routes/console.php`); necesita `php artisan schedule:run` en el
  cron del servidor.
- **Manual**: botón *Crear ahora* en el panel o `php artisan sistema:respaldar`.
- **Retención**: sólo se conservan los últimos `RESPALDOS_CONSERVAR` (14 por
  defecto); los más antiguos se eliminan al crear uno nuevo.

`pg_dump` y `pg_restore` deben estar instalados en el servidor donde corre la
aplicación (paquete `postgresql-client`; en imágenes Alpine,
`postgresql16-client` o el `postgresql-client` que ya instala el `Dockerfile`).
Si no están en el `PATH`, indica su carpeta en `RESPALDOS_RUTA_PG`
(por ejemplo `/usr/lib/postgresql/16/bin`).

Para **restaurar** una copia (reemplaza TODOS los datos actuales):

```bash
php artisan sistema:restaurar respaldo-20260101-020000.zip
# sin confirmación interactiva (scripts):
php artisan sistema:restaurar respaldo-20260101-020000.zip --forzar
php artisan optimize:clear
```

El comando ejecuta `pg_restore --clean --if-exists` sobre la conexión
configurada y copia `privado/` de vuelta a `storage/app/private`. Por seguridad
la restauración sólo está disponible desde la consola, no desde el panel.

### Roles y permisos

Los módulos, sus acciones y los roles predefinidos viven en
`config/odontosuite.php`. Es la única fuente de verdad: alimenta la barra de
navegación, la pantalla de roles y el seeder de permisos. Al agregar un módulo
o una acción ahí, vuelve a sembrar los permisos:

```bash
php artisan db:seed --class=RolPermisoSeeder
```

---

## Estructura

```
app/
├── Console/Commands/         citas:recordar, sistema:respaldar, sistema:restaurar
├── Http/
│   ├── Controllers/Admin/    25 controladores del panel
│   ├── Controllers/Auth/     login con doble factor, registro, recuperación, Socialite
│   ├── Controllers/Portal/   portal del paciente
│   └── Middleware/           ajustes compartidos, cuentas inactivas, contraseña temporal, portal
├── Mail/                     confirmación de cita, comprobante de pago y código de acceso
├── Models/                   24 modelos Eloquent (+ trait Auditable)
└── Services/
    ├── AgendaService         cupos, duración por tratamiento y detección de cruces
    ├── InventarioService     único punto de cambio de existencias
    ├── QrService             códigos QR en SVG sin dependencias de imagen
    └── RespaldoService       copias de seguridad (pg_dump + archivos privados) y restauración
lang/es/                      validación, autenticación y paginación en español
resources/views/
├── admin/                    vistas del panel por módulo
├── auth/                     pantallas de acceso
├── componentes/              componentes Blade (kpi, campo, odontograma…)
├── emails/                   plantillas de correo
├── layouts/                  admin, portal, público y autenticación
├── pdf/                      recibo, historia clínica, documentos y 4 reportes
├── portal/                   portal del paciente
└── publico/                  landing y reserva en línea
```

### Sobre el dialecto SQL

Algunas consultas de reportes usan sintaxis propia de PostgreSQL
(`FILTER (WHERE …)`, `TO_CHAR`, `ilike`). Si migras a otro motor, revisa
`ReporteController`, `HomeController` y los `scopeBuscar` de los modelos.

---

## Licencia

MIT.

## Transmisión de documentos fiscales

Al emitir una factura o comprobante, OdontoSuite intenta transmitirlo al
proveedor configurado en `FACTURACION_PROVEEDOR` y guarda en el documento el
proveedor, el estado de transmisión (`NO_APLICA`, `PENDIENTE`, `ACEPTADO`,
`RECHAZADO`), la respuesta cruda y el sello devuelto. Un documento rechazado
se puede reintentar desde su ficha con **Reintentar transmisión**.

```dotenv
FACTURACION_PROVEEDOR=simulado   # simulado | http | ninguno
FACTURACION_ENDPOINT=            # solo para http
FACTURACION_TOKEN=               # solo para http (Bearer)
```

- `simulado` (por defecto) acepta todo y devuelve un sello aleatorio. Sirve
  para desarrollo y para clínicas sin obligación de transmitir.
- `ninguno` deja los documentos como `NO_APLICA`: solo se conservan localmente.
- `http` es un **contrato genérico**: envía el documento como JSON (`POST` a
  `FACTURACION_ENDPOINT` con `Authorization: Bearer FACTURACION_TOKEN`, 15 s de
  espera) y espera una respuesta JSON con `aceptado`, `sello`, `codigo` y
  `mensaje`. Una respuesta 2xx sin `aceptado` se toma como aceptada; un error
  HTTP o de conexión deja el documento en `RECHAZADO` con el mensaje recibido.
  Cualquier pasarela intermedia que hable ese contrato funciona sin tocar código.

La integración con cada administración tributaria (Hacienda, SIN, SUNAT, DIAN,
etc.) se implementa como **un driver más**: una clase que cumple
`App\Services\FacturacionElectronica\ProveedorFiscal` (método
`transmitir(DocumentoFiscal): RespuestaFiscal`) y que se registra en
`App\Services\FacturacionElectronicaService::driver()`. Ahí van el firmado, el
formato exigido por el organismo y la lectura de su respuesta; el resto del
módulo (numeración, PDF, reintentos, auditoría) no cambia.

---

## Sucursales (multi-sede)

Una clínica puede operar varias sedes. El módulo **Sucursales**
(`Configuración → Sucursales`, permisos `sucursales.*`) mantiene el catálogo:
nombre, código corto en mayúsculas, dirección, contacto, color y las marcas
*principal* y *activa*. Solo puede haber una sede principal; marcar otra
desmarca la anterior. No se elimina la única sede activa ni una con citas o
cobros: en ese caso se desactiva.

- **Sede activa.** Un usuario no ligado a una sede elige con cuál trabaja desde
  el selector del navbar (junto a la campana); la elección vive en sesión
  (`App\Support\SucursalActiva`). "Todas las sedes" (`null`) muestra todo.
- **Usuario ligado a una sede.** En el formulario de usuarios, el campo
  *Sucursal* (visible solo para super administradores o quien tenga
  `sucursales.editar`) ata la cuenta a una sede: verá únicamente esa y no podrá
  cambiarla.
- **Etiquetado.** Horarios, citas, recibos, insumos y lista de espera llevan
  `sucursal_id`. Al crear un registro se usa la sede del formulario, si no la
  sede activa y, cuando existe una sola sede, la principal. Los formularios solo
  muestran el campo cuando hay más de una sede activa; los listados de horarios,
  caja e inventario muestran la columna y el filtro por sede, y los totales de
  caja respetan la sede activa.
- **Demo.** `SucursalSeeder` crea *Sede Central* (`CENTRAL`, principal) y
  *Sede Sur* (`SUR`); `DatabaseSeeder` etiqueta con la principal los datos de
  demostración que nacen sin sede.

Pruebas: `DB_PASSWORD=postgres php artisan test --filter=SucursalTest`.

## Portal del paciente: reservas, pagos en línea y firma

### Reserva de citas desde el portal

El paciente identificado reserva sin volver a escribir sus datos: elige
especialidad, motivo de consulta, profesional, fecha y cupo (`/portal/reservar`,
`PortalReservaController`). La lógica compartida con la reserva pública por
token vive en el trait `App\Http\Controllers\Concerns\ReservaCitas` (catálogo
reservable, cupos con anticipación mínima, ventana de días, creación de la cita
`PENDIENTE` con origen `ONLINE` y correo de confirmación encolado). Se activa con
`Ajuste::portal_reservas_activas`; apagado, el botón desaparece del portal y las
rutas redirigen a *Mis citas* con un aviso.

### Pagos en línea

Con `Ajuste::pagos_online_activos` cada recibo con saldo muestra **Pagar en
línea** en *Mis pagos*. `PortalPagoController@iniciar` abre un `PagoOnline`
`PENDIENTE` por el saldo y envía al paciente a la pasarela; al confirmarse, el
monto se abona al recibo (`monto_pagado`, `recalcular()`), se anota en `notas`
y queda en la auditoría. La acreditación es idempotente: bloquea el recibo y el
intento dentro de una transacción, y un segundo aviso no vuelve a sumar.

`App\Services\PasarelaPagoService` elige el driver
(`App\Services\Pasarela\ProveedorPago`) por `config('services.pasarela.proveedor')`:

| Variable | Descripción |
| --- | --- |
| `PASARELA_PROVEEDOR` | `simulado` (por defecto; sin cobro real) o `stripe`. |
| `STRIPE_SECRET` | Clave secreta de la API de Stripe (`sk_…`). |
| `STRIPE_WEBHOOK_SECRET` | Secreto del endpoint de webhook (`whsec_…`). |

- **simulado**: muestra una "pasarela" local con los botones *Pagar* y
  *Cancelar*, que llevan a las rutas de retorno firmadas
  (`pagos-online/retorno/{intento}/{exito|cancelado}`); el retorno con éxito
  marca el intento como `PAGADO`.
- **stripe**: crea una *Checkout Session* (`POST /v1/checkout/sessions`, monto en
  centavos, `metadata.pago_online_id`). El retorno del navegador solo muestra el
  estado; el cobro se confirma por webhook en `POST /pagos-online/webhook/stripe`
  (`checkout.session.completed` → `PAGADO`, `…expired` → `CANCELADO`,
  `…async_payment_failed` → `FALLIDO`). La cabecera `Stripe-Signature` se
  verifica con HMAC SHA-256 sobre `t.payload` y tolerancia de 5 minutos.
  Configura en Stripe la URL del webhook con tu `APP_URL`.

### Firma del consentimiento desde el portal

Los consentimientos informados emitidos y sin firma muestran **Firmar** en *Mis
documentos*. La vista presenta el texto completo y el mismo pad de firma del
panel; el PNG se valida igual que en el admin y se guarda en el disco privado
(`DocumentoClinico::DISCO_FIRMAS`) con `firmado_en` y rastro de auditoría. Un
documento ajeno responde 404 y uno ya firmado no se modifica.

Pruebas: `DB_PASSWORD=postgres php artisan test --filter='PortalReservaTest|PagoOnlineTest|PortalFirmaTest'`.

## API

OdontoSuite expone una API REST (`/api/v1`) autenticada con tokens personales
de Laravel Sanctum para integrar centrales telefónicas, chatbots, apps móviles
o sistemas contables:

- Los tokens se crean desde **Mi perfil → Tokens de API** (permiso `api.usar`,
  incluido en SUPER ADMINISTRADOR y ADMINISTRADOR) y heredan los permisos del
  usuario que los creó; se revocan desde la misma pantalla.
- Endpoints: `yo`, `pacientes` (listar, buscar, crear, ver), `citas` (listar
  con filtros, agendar con las mismas reglas de agenda del panel, ver, cambiar
  estado), `agenda/horas`, `doctores`, `especialidades`, `tratamientos`,
  `presupuestos` y `pagos` (solo lectura, con detalles).
- Paginación estándar (`page`, `per_page` ≤ 100), errores en JSON y límite de
  120 peticiones por minuto.

```bash
curl -s "https://tu-dominio/api/v1/citas?fecha=2026-09-16" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```

La referencia completa, con parámetros, ejemplos `curl` y respuestas, está en
[docs/api.md](docs/api.md).

## Licencia (CONTROL)

DENTAL-PRO se licencia desde **CONTROL**, el panel central de la agencia. Cada
instalación se activa con una clave `CTL-XXXX-XXXX-XXXX-XXXX` atada a un equipo o
dominio y recibe un token firmado (Ed25519) que se verifica **sin internet** con
la clave pública embebida; el token se renueva con un latido diario y vale 7 días
sin red.

1. Obtén la clave pública una sola vez:
   `curl -s https://control.tuagencia.com/api/v1/licencias/clave-publica` →
   copia `clave_publica` en `CONTROL_CLAVE_PUBLICA`.
2. Variables (`.env`): `CONTROL_ACTIVO=true` (en desarrollo y pruebas `false`:
   no se exige licencia), `CONTROL_URL`, `CONTROL_CLAVE_PUBLICA`, opcionalmente
   `CONTROL_LICENCIA` y `CONTROL_HUELLA`, y `APP_VERSION` (versión que se informa
   a CONTROL).
3. Registra la clave: desde la pantalla **`/licencia`** (en el primer arranque
   cualquier usuario autenticado puede hacerlo; después solo quien tiene
   `ajustes.editar` o es SUPER ADMINISTRADOR) o con
   `php artisan licencia:activar CTL-XXXX-XXXX-XXXX-XXXX`. Queda en
   `storage/app/control/licencia.json` junto con el último token válido.

La pantalla `/licencia` (también enlazada desde Ajustes → *Licencia del sistema*)
muestra estado, clave, plan, vencimiento, soporte, "funciona sin internet hasta",
equipo (huella), versión instalada y aviso de versión nueva, con los botones
**Reactivar / verificar ahora**, **Código de emergencia (72 h)** e ingreso o
cambio de clave. Estados: `activa` entra; `mora` (vencida, en gracia) entra con
aviso; `suspendida`, `vencida` y `revocada` bloquean el panel, el portal, la
reserva pública y la API (`402 {ok:false, error, codigo}`); `login`, `/` y
`/licencia` siempre se pueden abrir.

- **Sin internet o CONTROL caído**: el sistema sigue hasta `expira_en` del token.
  Si se agota, un vendedor o admin emite desde CONTROL un **código de emergencia**
  para la huella del equipo, válido 72 h, que se pega en `/licencia` y se acepta
  sin red.
- **Escritorio / instalador**: fija `CONTROL_HUELLA` con el identificador del
  equipo (si la app corre en `localhost` se usa un hash del nombre del equipo) y
  ejecuta `php artisan licencia:activar {clave}`; el latido diario es
  `php artisan licencia:latido` (ya programado en el scheduler).

Detalle completo en [docs/licencia.md](docs/licencia.md). Pruebas:
`DB_PASSWORD=postgres php artisan test --filter=LicenciaTest`.
