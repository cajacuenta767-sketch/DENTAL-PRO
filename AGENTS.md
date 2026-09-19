# Guía para agentes de IA

Todo lo necesario para clonar OdontoSuite, dejarlo funcionando y trabajar en él
sin romper nada. Si solo quieres **ponerlo en marcha**, salta a *Arranque rápido*
y para cuando `./scripts/verificar.sh` diga «Todo en orden».

Para desplegar en un servidor, la guía es [`docs/DESPLIEGUE.md`](docs/DESPLIEGUE.md).
Para el plan de pruebas, [`docs/QA.md`](docs/QA.md).

---

## Qué es esto

Sistema de gestión para clínicas dentales: **Laravel 12 + Blade + Tabler UI +
PostgreSQL**. Sitio público con reservas por QR, panel administrativo con 21
módulos, roles y permisos por acción, historia clínica, odontograma,
presupuestos, caja, inventario, facturación y reportes en PDF.

**Todo el código, los datos y la interfaz están en español**: nombres de tablas
(`pacientes`, `citas`, `movimientos_inventario`), de modelos (`Paciente`,
`Pago`), de columnas (`numero_documento`, `stock_resultante`) y de métodos
(`guardarDetalles`, `sincronizarEstado`). Mantén esa convención: un
`PatientController` junto a `PacienteController` haría el código ilegible.

---

## Arranque rápido

```bash
git clone <url-del-repositorio> odontosuite
cd odontosuite

# PostgreSQL tiene que estar corriendo y la contraseña de DB_PASSWORD en .env
# debe coincidir con la del usuario. Si aún no existe .env, el script lo crea.
./scripts/instalar.sh

php artisan serve
```

El script comprueba requisitos, instala dependencias, crea las dos bases
(`odontosuite` y `odontosuite_testing`), migra, siembra la clínica de
demostración, enlaza `storage`, compila los assets y verifica el resultado.
Es idempotente y **no siembra dos veces**: si la base ya tiene datos, lo avisa
y sigue.

Variantes:

| Comando | Para qué |
|---|---|
| `./scripts/instalar.sh` | Clínica demo: 50 pacientes, 200 citas, 6 meses de historial |
| `./scripts/instalar.sh --en-blanco` | Solo roles, permisos, ajustes y las 4 cuentas |
| `./scripts/instalar.sh --sin-node` | Omite npm; la app funciona pero sin estilos |
| `./scripts/verificar.sh` | Comprueba una instalación existente; sale 1 si algo falta |

Si PostgreSQL no está levantado, el script para con instrucciones concretas en
lugar de dejar la instalación a medias.

### Cuentas de la demo

| Correo | Contraseña | Rol |
|---|---|---|
| `admin@admin.com` | `admin123` | Super administrador |
| `admin@clinica.com` | `admin123` | Administrador |
| `secretaria@clinica.com` | `recepcion123` | Recepción |
| `sofia.arancibia@clinica.com` | `doctor123` | Doctor |

Úsalas para probar permisos: cada rol ve módulos distintos.

---

## Requisitos

- PHP **8.2+** con `pdo_pgsql`, `mbstring`, `gd`, `zip`, `intl`
- Composer 2 · PostgreSQL **14+** · Node.js 20+ (solo para los assets)

**PostgreSQL no es opcional.** Los reportes y los buscadores usan sintaxis
propia del motor (`FILTER (WHERE …)`, `TO_CHAR`, `ilike`). En SQLite o MySQL el
panel arranca pero `ReporteController`, `HomeController` y los `scopeBuscar`
fallan. Las pruebas también corren contra PostgreSQL.

---

## Comandos

```bash
php artisan serve                  # http://localhost:8000
npm run dev                        # Vite en watch mientras editas estilos

php artisan test                   # 130 pruebas; debe quedar todo en verde
php artisan test --filter=PagoTest # una sola
./vendor/bin/pint                  # aplica el formato (obligatorio antes de commitear)
./vendor/bin/pint --test           # solo comprueba

php artisan migrate:fresh --seed --force   # reiniciar la base desde cero
```

---

## Mapa del código

```
app/
├── Http/Controllers/
│   ├── Admin/               23 controladores del panel, uno por módulo
│   ├── Auth/                login, registro, recuperación, Socialite, verificación
│   ├── PerfilController     datos y contraseña del usuario en sesión
│   ├── PublicoController    landing
│   └── ReservaPublicaController   reserva por token, sin autenticación
├── Http/Middleware/
│   ├── CompartirAjustes     inyecta los ajustes de la clínica en TODAS las vistas
│   └── VerificarUsuarioActivo  saca a la calle a quien fue desactivado
├── Rules/CitaDelPaciente    valida que la cita pertenezca al paciente cobrado
├── Models/                  20 modelos Eloquent
├── Mail/                    confirmación de cita y comprobante de pago
└── Services/
    ├── AgendaService        único cálculo de cupos libres (panel y web pública)
    ├── InventarioService    único punto que cambia existencias
    └── QrService            QR en SVG, sin dependencias de imagen

config/odontosuite.php       módulos, acciones, roles y proxies de confianza
database/migrations/         22 migraciones
database/seeders/            10 seeders (ver abajo)
lang/es/                     traducciones; sin esto la UI muestra «validation.required»
resources/views/
├── admin/                   vistas del panel por módulo
├── componentes/             componentes Blade sueltos (kpi, campo, odontograma…)
├── layouts/                 admin, público, autenticación
├── pdf/                     recibo, historia clínica, presupuesto, receta,
│                            documento fiscal y los 4 reportes
└── publico/                 landing y formulario de reserva
scripts/                     instalar.sh y verificar.sh
tests/
├── CasoClinico.php          base: clínica montada con roles, doctor y paciente
├── Feature/                 19 archivos, uno por área
└── Unit/                    cálculo fiscal puro
```

### Seeders

`DatabaseSeeder` los encadena en este orden, que importa:

`AjusteSeeder` → `RolPermisoSeeder` → `UsuarioSeeder` → `CatalogoSeeder` →
`EquipoSeeder` → `DemoClinicaSeeder` → `AseguradoraSeeder` →
`InventarioSeeder` → `ClinicaAvanzadaSeeder`

Los tres primeros son el mínimo para que el panel abra; el resto es la demo.
**No son idempotentes**: `citas` tiene un índice único por doctor y hora, así
que correrlos dos veces revienta con una violación de unicidad. Para reiniciar,
usa `migrate:fresh --seed`, nunca `db:seed` sobre una base con datos.

---

## Cómo funciona por dentro

### Permisos

`config/odontosuite.php` es la **única fuente de verdad**: define los módulos,
sus acciones y los roles predefinidos. De ahí salen la barra de navegación, la
pantalla de roles y el seeder de permisos (82 permisos, 5 roles).

Al agregar un módulo o una acción, edita ese archivo y vuelve a sembrar:

```bash
php artisan db:seed --class=RolPermisoSeeder --force
```

Las rutas se protegen con `->middleware('permission:modulo.accion')`.

### Los flujos que conectan módulos

- **Odontograma → presupuesto → ejecución → cobro → factura.** Los hallazgos
  del odontograma precargan un presupuesto; al aprobarlo se marcan líneas como
  ejecutadas; al cobrar, el recibo se arma **solo con lo ejecutado**; desde el
  recibo se emite el documento fiscal.
- **Seguro.** La cobertura de la aseguradora se aplica sobre el neto **ya
  descontado** y respeta el tope anual.
- **Inventario.** Las existencias solo cambian por movimientos, siempre vía
  `InventarioService`. Cada movimiento guarda su `stock_resultante` y el
  sistema rechaza cualquier salida que dejaría el stock negativo. No escribas
  `stock_actual` a mano.
- **Agenda.** `AgendaService` calcula los cupos a partir del horario del doctor
  y las citas tomadas. El panel y la reserva pública usan el mismo servicio: si
  tocas uno, cambian los dos.
- **Facturación.** El documento fiscal guarda copia de las líneas facturadas, de
  modo que editar el recibo después no altera lo ya emitido.

---

## Trampas conocidas

Cosas que ya costaron un fallo. Léelas antes de tocar el área correspondiente.

**Las traducciones no son opcionales.** La app corre con `APP_LOCALE=es` y
`fallback_locale=es`. Si falta `lang/es`, Laravel **no falla**: muestra la clave
cruda (`validation.required`, `passwords.user`, `pagination.next`) en cada
formulario y en cada listado. `IdiomaTest` lo vigila. Si agregas mensajes,
agrégalos ahí; si agregas campos a un formulario, añade su nombre legible en
`lang/es/validation.php` → `attributes`.

**`assertSessionHasErrors()` no comprueba los mensajes**, solo las claves. Una
prueba verde no garantiza que el usuario vea texto en español. Por eso existe
`IdiomaTest`.

**Reglas `nullable` y acceso al array validado.** Si una regla es `nullable` y
el cliente no envía la clave, esa clave **no aparece** en el array que devuelve
`validate()`. Usar `$datos['campo']` directamente da error 500. Escribe
`($datos['campo'] ?? null)`. Pasó en `PresupuestoController` y `PagoController`.

**`env()` fuera de `config/` devuelve `null` con `config:cache`.** Y en
`bootstrap/app.php` el contenedor todavía no tiene `config` resuelto, así que
`config()` tampoco sirve ahí. Configuración nueva → un archivo de `config/`,
leído desde un service provider o un controlador. Los proxies de confianza se
resolvieron así (`config/odontosuite.php` + `AppServiceProvider::boot()`).

**`CompartirAjustes` consulta la base en cada petición web.** Si la base no está
migrada o la tabla `ajustes` está vacía, **todo** el sitio da 500, incluida la
página de login. Cuando veas 500 en todas partes, mira primero ahí.

**Los correos se envían de forma síncrona.** No hay colas ni tareas programadas;
ningún `Mailable` implementa `ShouldQueue`. No hace falta worker ni cron, pero
un SMTP lento retrasa la respuesta. En desarrollo, `MAIL_MAILER=log` los deja en
`storage/logs/laravel.log`.

**Rutas de acción usan PATCH o DELETE**, no POST: anular un recibo o una
factura, cambiar el estado de una cita o de un presupuesto, ejecutar una línea.
Desde un formulario Blade va `@method('PATCH')`; desde un cliente HTTP, el campo
`_method`.

**El odontograma recibe `piezas` como un JSON en un solo campo**, no como
`hallazgos[11][oclusal]`. El controlador descarta piezas y caras inválidas y
normaliza cualquier estado desconocido a `sano`, en silencio. El vocabulario
válido está en `Odontograma::ESTADOS` y `Odontograma::CARAS`, **en minúsculas**.

---

## Trabajar en el repo

1. **Antes de tocar nada**: `php artisan test` en verde. Si ya falla, arregla
   eso primero o dilo.
2. **Código y textos en español**, siguiendo lo que ya hay.
3. **Comentarios solo donde el porqué no sea obvio.** El código de alrededor es
   parco: imítalo, no lo llenes de comentarios.
4. **Prueba lo que arreglas.** Una prueba de regresión solo vale si **falla** al
   revertir el arreglo: compruébalo antes de darla por buena.
5. **`./vendor/bin/pint`** antes de commitear. CI y la revisión lo esperan.
6. **Un módulo nuevo** toca cuatro sitios: `config/odontosuite.php` (módulo y
   acciones), `routes/web.php` (rutas con su `permission:`), el controlador y
   las vistas. Después, resiembra los permisos.

### Antes de dar algo por terminado

```bash
php artisan test            # 130 en verde (más las que agregues)
./vendor/bin/pint --test    # sin hallazgos
npm run build               # sin errores
./scripts/verificar.sh      # Todo en orden
```

Y para cambios que tocan la interfaz, levanta el servidor y míralo: la suite no
ve los estilos, ni los PDF impresos, ni el QR escaneado desde un móvil.
`docs/QA.md` tiene la lista completa de lo que se revisa a mano y el QA en vivo.
