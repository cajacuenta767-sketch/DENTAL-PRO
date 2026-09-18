# Plan de QA — OdontoSuite

Cómo se verifica que cada apartado del sistema funciona, qué cubre hoy la
suite automática y qué conviene revisar a mano antes de publicar.

## 1. Montar el entorno de pruebas

```bash
composer install
cp .env.example .env && php artisan key:generate

# PostgreSQL: el proyecto usa pgsql tanto en local como en las pruebas
createdb odontosuite && createdb odontosuite_testing
php artisan migrate --seed          # deja la clínica demo cargada

npm ci && npm run build             # opcional para las pruebas, necesario para usar la app
```

Las credenciales de la clínica demo las imprime el seeder al terminar
(`admin@admin.com / admin123` y una cuenta por rol).

## 2. Suite automática

```bash
php artisan test                    # todo
php artisan test --testsuite=Unit   # cálculos puros, sin base de datos
php artisan test --filter=HumoPanelTest
./vendor/bin/pint --test            # estilo de código
```

La suite **no** necesita `npm run build`: si falta el manifiesto de Vite,
las etiquetas de assets se omiten (`tests/TestCase.php`). Cuando el
manifiesto existe, las vistas se renderizan con él, de modo que un build
roto tampoco pasa desapercibido.

## 3. Qué cubre cada prueba

| Apartado | Prueba | Qué verifica |
| --- | --- | --- |
| Todo el panel | `HumoPanelTest` | Recorre **todas** las rutas GET con la clínica demo y un super administrador: ninguna puede devolver error ni rebotar al login |
| Acceso | `AutenticacionTest` | Login, registro, usuario inactivo, landing pública |
| Permisos | `PermisoTest` | Cada rol alcanza solo sus módulos; el menú refleja los permisos |
| Cuentas | `CuentaTest` | Alta de usuarios con rol, roles y permisos, no quedarse sin super administrador, perfil y cambio de contraseña |
| Catálogos | `CatalogoTest` | Especialidades, tratamientos, aseguradoras, horarios e insumos: alta, validaciones, cruces de horario y borrado protegido |
| Pacientes y doctores | `FichaTest` | Alta, documento único, fotografía, y no borrar a quien tiene recibos o citas |
| Citas | `CitaTest` | Agenda, cupos ocupados, horarios del doctor, cancelaciones |
| Agenda y buscador | `BusquedaAgendaTest` | Turnos del día, filtro por doctor, tablero y buscador global respetando permisos |
| Historia clínica | `HistorialClinicoTest` | Registro de consultas, fecha no futura, PDF y coherencia con la cita |
| Odontograma | `OdontogramaTest` | Hallazgos por cara, dentición temporal, conteo de piezas |
| Imagenología | `EstudioImagenTest` | Carga, formatos permitidos, sustitución, descarga y borrado del archivo |
| Recetas y certificados | `DocumentoClinicoTest` | Folios correlativos, vigencia, anulación, PDF |
| Presupuestos | `PresupuestoTest` | Cobertura del seguro, descuentos, tope anual, ejecución y facturación |
| Caja | `PagoTest` | Cobros totales y parciales, saldo, anulación, correlativos |
| Facturación | `FacturacionTest` | Desglose de IVA, número de control, correlativos por serie, anulación |
| Inventario | `InventarioTest` | Entradas, salidas, ajustes, stock nunca negativo, alertas de mínimo |
| Reportes | `ReporteTest` | Las cuatro secciones y su exportación a PDF |
| Reservas en línea | `ReservaOnlineTest` | Página pública por token, anticipación mínima y máxima, cupos, QR |
| Cálculo fiscal | `Unit\DocumentoFiscalTest` | Desglose de IVA sin perder centavos y formato del número de control |

`HumoPanelTest` es la red de seguridad: cualquier vista, consulta o
relación que se rompa en cualquier módulo aparece ahí sin tener que
escribir una prueba nueva.

## 4. Revisión manual antes de publicar

Lo que la suite no puede ver:

1. **Assets y diseño** — `npm run build` y recorrer el panel en un
   navegador; revisar el modo oscuro y el ancho de móvil.
2. **Correo** — confirmación de cita, reenvío y recibo: con
   `MAIL_MAILER=log` basta con leer `storage/logs`.
3. **Login social** — Google y GitHub necesitan credenciales reales en
   `.env`; sin ellas los botones no deben aparecer.
4. **Reserva en línea de punta a punta** — abrir el enlace público y el QR
   desde un móvil y completar una reserva.
5. **Impresiones** — recibo, presupuesto, historia clínica y documento
   fiscal: comprobar los PDF en papel tamaño carta.
6. **Carga de archivos grandes** — estudios de hasta 20 MB según el
   límite de PHP del servidor (`upload_max_filesize`, `post_max_size`).

## 5. Criterio para dar una versión por buena

- `php artisan test` en verde.
- `./vendor/bin/pint --test` sin hallazgos.
- `npm run build` sin errores.
- `php artisan migrate:fresh --seed` funciona desde cero.
- Los seis puntos de revisión manual, comprobados.
