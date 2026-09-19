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
| Idioma | `IdiomaTest` | Los mensajes de validación, acceso, contraseña y paginación salen en español y no como clave cruda |

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

## 5. QA en vivo sobre la aplicación corriendo

La suite corre contra una base recién migrada. Estas comprobaciones se hacen
con el servidor levantado y la clínica demo cargada, porque tocan cosas que la
suite no ve: el manifiesto real de Vite, el enlace de `storage`, los binarios
que devuelven los PDF y el QR, y el comportamiento con la configuración
cacheada.

```bash
php artisan migrate --seed && npm run build
php artisan storage:link
php artisan serve
```

1. **Recorrer todas las rutas GET** con un super administrador: ninguna debe
   devolver 5xx ni rebotar al login.
2. **Descargas binarias** — recibo, historia clínica, documento clínico,
   presupuesto, documento fiscal y las cuatro secciones de reportes deben
   devolver `application/pdf`; el QR, `image/svg+xml`.
3. **Modo producción** — con `APP_ENV=production`, `APP_DEBUG=false` y los
   cuatro cachés (`config`, `route`, `view`, `event`) el panel completo debe
   seguir respondiendo. Es donde aparecen los fallos que solo ocurren con la
   configuración cacheada.
4. **Base vacía** — con solo `AjusteSeeder`, `RolPermisoSeeder` y
   `UsuarioSeeder`, todos los módulos y los cuatro PDF de reportes deben
   abrir sin datos. Es el arranque real de una clínica nueva.
5. **Campos opcionales ausentes** — enviar un presupuesto o un recibo cuya
   línea no incluya los campos `nullable` (pieza, cara, tratamiento). Debe
   guardarse, no dar 500.
6. **Mensajes en español** — provocar un error de validación en el panel y en
   la reserva pública: no debe aparecer `validation.*` ni `passwords.*`.
7. **Proxy TLS** — si hay proxy delante, con `TRUSTED_PROXIES` definido el
   enlace y el QR de **Configuración → Turnos online** deben salir en `https`.
8. **Log limpio** — `storage/logs/laravel.log` sin `ERROR` tras el recorrido.

## 6. Criterio para dar una versión por buena

- `php artisan test` en verde.
- `./vendor/bin/pint --test` sin hallazgos.
- `npm run build` sin errores.
- `php artisan migrate:fresh --seed` funciona desde cero.
- Los seis puntos de revisión manual, comprobados.
- Los ocho puntos del QA en vivo, comprobados.
- `docs/DESPLIEGUE.md` seguido de principio a fin en un servidor limpio.
