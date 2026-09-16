# Despliegue en producción

La imagen oficial sirve Laravel con Apache en el puerto `8000`; además levanta
PostgreSQL, un worker de colas y el programador. La base de datos sólo se publica
en `127.0.0.1`, no hacia Internet. La aplicación también se liga a localhost por
defecto para que un proxy inverso del mismo servidor sea el único punto público;
puedes cambiarlo con `APP_BIND_IP` si tu plataforma necesita otro enlace.

## 1. Preparar el entorno

Copia `.env.example` a `.env` y cambia, como mínimo:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://dental.tudominio.com
APP_KEY=base64:CLAVE_GENERADA
LOG_LEVEL=warning

DB_DATABASE=odontosuite
DB_USERNAME=odontosuite
DB_PASSWORD=UNA_CLAVE_LARGA_Y_UNICA

SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
MAIL_MAILER=smtp
MAIL_HOST=smtp.tuproveedor.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=no-reply@tudominio.com
```

Genera `APP_KEY` en una máquina con PHP/Composer o con una ejecución temporal:

```bash
docker compose run --rm --no-deps -e APP_ENV=local app php artisan key:generate --show
```

No reutilices credenciales de desarrollo. Configura también el proveedor fiscal,
la pasarela de pago, mensajería y CONTROL según los módulos que vayas a activar.

## 2. Iniciar y verificar

```bash
docker compose build --pull
docker compose up -d
docker compose ps
curl --fail http://127.0.0.1:8000/up
```

El arranque falla deliberadamente si `APP_KEY` está vacío. El contenedor `app`
ejecuta las migraciones con `--force`, crea el enlace de archivos públicos y
cachea configuración, rutas y vistas. `queue` y `scheduler` esperan a que la
aplicación esté saludable.

## 3. HTTPS y copias de seguridad

Publica el puerto 8000 detrás de un proxy inverso (Caddy, Nginx, Traefik o el
balanceador del proveedor) con certificado TLS. No expongas el puerto de
PostgreSQL fuera del servidor.

Antes de recibir datos reales, ejecuta y descarga una copia de prueba:

```bash
docker compose exec app php artisan sistema:respaldar
```

Conserva una copia cifrada fuera del servidor y prueba el procedimiento de
restauración. Los volúmenes `db-data`, `storage-data` y `storage-logs` contienen
el estado persistente y no deben eliminarse durante una actualización.

## 4. Actualizar

```bash
docker compose build --pull
docker compose up -d --remove-orphans
docker compose ps
```

Revisa los logs si un servicio no queda saludable:

```bash
docker compose logs --tail=200 app queue scheduler
```

## Lista de salida

- DNS y HTTPS funcionan con el dominio final.
- `APP_DEBUG=false`, `LOG_LEVEL=warning` y `APP_URL` usa `https://`.
- Contraseñas y tokens son únicos y no están versionados.
- SMTP y el worker procesan un correo de prueba.
- El scheduler ejecuta recordatorios y respaldos.
- La pasarela y el webhook se probaron en modo real antes de habilitar pagos.
- El proveedor fiscal corresponde al país; `simulado` no transmite documentos.
- CONTROL está activado y la instalación tiene una licencia válida.
- Existe una copia externa y una restauración verificada.
