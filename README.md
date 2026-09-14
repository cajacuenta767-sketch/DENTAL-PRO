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
| **Imagenología** | Panorámicas y fotos clínicas con visor de zoom y descarga del original |
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
marca cada cita avisada para no repetir el envío.

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
├── Console/Commands/         citas:recordar
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
    └── QrService             códigos QR en SVG sin dependencias de imagen
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
