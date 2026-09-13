# OdontoSuite · Sistema de Gestión Odontológica

Sistema completo para clínicas dentales construido con **Laravel 12**, **Blade**,
**Tabler UI** y **PostgreSQL**. Incluye sitio público, panel administrativo con
roles y permisos, agenda de citas, historia clínica digital, odontograma
interactivo, caja y centro de reportes con exportación a PDF.

---

## Módulos

| Módulo | Qué resuelve |
|---|---|
| **Home** | Panel con 8 indicadores, evolución de citas a 6 meses y reparto por estado |
| **Ajustes** | Datos de la clínica, divisa, logotipo, intervalo de cita y recordatorios |
| **Roles** | 5 roles predefinidos y 54 permisos por acción, editables desde la interfaz |
| **Usuarios** | Altas, estado activo/inactivo y asignación de roles |
| **Pacientes** | Ficha completa: antecedentes, alergias, contacto de emergencia y saldo |
| **Especialidades** | Catálogo con color identificador |
| **Tratamientos** | Catálogo con precio y duración, base de la agenda y la caja |
| **Doctores** | Ficha profesional, colegiatura y enlace con su usuario del sistema |
| **Horarios** | Disponibilidad semanal por turno, con detección de solapamientos |
| **Citas** | Token de confirmación, 5 estados, filtros y correo automático |
| **Mi Agenda** | Vista diaria de cupos libres y ocupados por doctor |
| **Historia Clínica** | Registro por consulta con diagnóstico, tratamiento y receta en PDF |
| **Odontograma** | Mapa interactivo por pieza y cara (FDI), adulto e infantil |
| **Caja y Pagos** | Recibos con detalle, 4 métodos de cobro, saldos, anulación y PDF |
| **Reportes** | 4 secciones (financiero, productividad, padrón, rentabilidad) con PDF |

---

## Requisitos

- PHP **8.2+** con las extensiones `pdo_pgsql`, `mbstring`, `gd`, `zip` e `intl`
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

> Cambia estas contraseñas antes de poner el sistema en producción.

El seeder genera 8 especialidades, 30 tratamientos, 10 doctores con 48 turnos,
50 pacientes, 200 citas, historias clínicas, odontogramas y recibos repartidos
en seis meses, para que el panel y los reportes tengan datos reales desde el
primer arranque.

---

## Desarrollo

```bash
npm run dev        # Vite en modo watch
php artisan test   # Suite de pruebas
```

Las pruebas corren contra una base aparte. Créala una sola vez:

```bash
createdb odontosuite_testing
```

---

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
├── Http/
│   ├── Controllers/Admin/    15 controladores del panel
│   ├── Controllers/Auth/     login, registro, recuperación, Socialite
│   └── Middleware/           ajustes compartidos y bloqueo de cuentas inactivas
├── Mail/                     confirmación de cita y comprobante de pago
├── Models/                   12 modelos Eloquent
└── Services/AgendaService    cálculo de cupos disponibles
resources/views/
├── admin/                    vistas del panel por módulo
├── auth/                     pantallas de acceso
├── componentes/              componentes Blade (kpi, campo, odontograma…)
├── emails/                   plantillas de correo
├── layouts/                  admin, público y autenticación
├── pdf/                      recibo, historia clínica y 4 reportes
└── publico/                  landing
```

### Sobre el dialecto SQL

Algunas consultas de reportes usan sintaxis propia de PostgreSQL
(`FILTER (WHERE …)`, `TO_CHAR`, `ilike`). Si migras a otro motor, revisa
`ReporteController`, `HomeController` y los `scopeBuscar` de los modelos.

---

## Licencia

MIT.
