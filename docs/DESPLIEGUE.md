# Despliegue en un VPS

Guía para dejar OdontoSuite corriendo en un servidor Ubuntu 22.04 / 24.04 o
Debian 12 con nginx, PHP-FPM y PostgreSQL. Los comandos asumen que trabajas
como un usuario con `sudo`.

El sistema **no usa colas ni tareas programadas**: los correos se envían en el
mismo momento de la petición. No hace falta un worker ni una entrada de cron.

> Existe `./scripts/instalar.sh`, pero está pensado para desarrollo: instala
> también las dependencias de desarrollo y no genera los cachés de producción.
> En un servidor sigue los pasos de esta guía. Lo que sí sirve en ambos sitios
> es `./scripts/verificar.sh`, para comprobar que no quedó nada suelto.

---

## 1. Paquetes del sistema

```bash
sudo apt update
sudo apt install -y nginx postgresql \
    php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring \
    php8.3-gd php8.3-zip php8.3-intl php8.3-xml php8.3-curl \
    git unzip curl
```

Composer y Node:

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

Comprueba que están las extensiones que el proyecto necesita:

```bash
php -m | grep -E 'pdo_pgsql|mbstring|gd|zip|intl'
```

---

## 2. Base de datos

```bash
sudo -u postgres psql
```

```sql
CREATE USER odontosuite WITH PASSWORD 'una_clave_larga_y_aleatoria';
CREATE DATABASE odontosuite OWNER odontosuite;
\q
```

---

## 3. Código y dependencias

```bash
sudo mkdir -p /var/www && cd /var/www
sudo git clone <url-del-repositorio> odontosuite
sudo chown -R $USER:www-data /var/www/odontosuite
cd /var/www/odontosuite

composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

> `--no-dev` deja fuera las herramientas de desarrollo. Si más adelante quieres
> correr `php artisan test` en el servidor, reinstala sin ese modificador.

---

## 4. Configuración

```bash
cp .env.example .env
php artisan key:generate
```

Edita `.env` con los valores de producción:

```dotenv
APP_NAME="Nombre de tu clínica"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=odontosuite
DB_USERNAME=odontosuite
DB_PASSWORD=una_clave_larga_y_aleatoria

# SMTP real: en producción no dejes MAIL_MAILER=log
MAIL_MAILER=smtp
MAIL_HOST=smtp.tu-proveedor.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="citas@tu-dominio.com"

CLINICA_MONEDA=BOB
CLINICA_ZONA_HORARIA=America/La_Paz
```

**`APP_DEBUG=false` es obligatorio en producción**: con `true`, cualquier error
muestra el código fuente y las credenciales al visitante.

### Si hay un proxy delante (Cloudflare, balanceador, nginx en otra máquina)

Sin esto, Laravel ve `http` aunque el visitante llegue por HTTPS y generaría
enlaces inseguros; el más visible es el **QR impreso de las reservas**.

```dotenv
TRUSTED_PROXIES=*
```

Usa `*` solo si el proxy es el único camino hacia la aplicación. Si no, pon la
lista de IPs separadas por comas. Con nginx y PHP-FPM en la misma máquina
(el caso de esta guía) no hace falta tocarlo.

---

## 5. Migrar y sembrar

```bash
php artisan migrate --force
php artisan storage:link
```

Para arrancar con la clínica de demostración cargada (útil para probar, **no**
para una clínica real):

```bash
php artisan db:seed --force
```

Si prefieres empezar en blanco, siembra solo los roles, permisos y ajustes:

```bash
php artisan db:seed --class=AjusteSeeder --force
php artisan db:seed --class=RolPermisoSeeder --force
php artisan db:seed --class=UsuarioSeeder --force
```

> **Cambia las contraseñas de las cuentas de demostración antes de abrir el
> sistema al público.** Están publicadas en el README.

---

## 6. Cachés de producción

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Repite estos cuatro comandos después de **cada** despliegue o cambio de `.env`:
con la configuración cacheada, editar `.env` no surte efecto hasta rehacer el
caché.

---

## 7. Permisos

nginx y PHP-FPM corren como `www-data`. Solo `storage/` y `bootstrap/cache/`
necesitan escritura:

```bash
sudo chown -R $USER:www-data /var/www/odontosuite
sudo find /var/www/odontosuite -type f -exec chmod 644 {} \;
sudo find /var/www/odontosuite -type d -exec chmod 755 {} \;
sudo chmod -R 775 /var/www/odontosuite/storage /var/www/odontosuite/bootstrap/cache
sudo chmod 640 /var/www/odontosuite/.env
```

---

## 8. nginx

`/etc/nginx/sites-available/odontosuite`:

```nginx
server {
    listen 80;
    server_name tu-dominio.com www.tu-dominio.com;

    root /var/www/odontosuite/public;
    index index.php;

    charset utf-8;
    client_max_body_size 20M;   # radiografías y fotos clínicas

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Los assets compilados llevan hash en el nombre: se pueden cachear fuerte.
    location /build/ {
        expires 1y;
        access_log off;
        add_header Cache-Control "public, immutable";
    }

    location ~ /\.(?!well-known).* { deny all; }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;
}
```

`client_max_body_size` debe acompañarse de los límites de PHP en
`/etc/php/8.3/fpm/php.ini`:

```ini
upload_max_filesize = 20M
post_max_size = 20M
```

Activa el sitio y recarga:

```bash
sudo ln -s /etc/nginx/sites-available/odontosuite /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl restart php8.3-fpm
```

---

## 9. HTTPS

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d tu-dominio.com -d www.tu-dominio.com
```

Certbot reescribe el bloque de nginx y programa la renovación. Después, asegúrate
de que `APP_URL` en `.env` empieza por `https://` y rehaz `config:cache`.

---

## 10. Comprobar que quedó bien

```bash
curl -I https://tu-dominio.com/up          # 200: la aplicación responde
curl -I https://tu-dominio.com/login       # 200: el panel carga
```

Y desde el navegador:

1. Entra con la cuenta de administrador y revisa que el **Home** muestre los
   indicadores (si no, la base no está migrada o sembrada).
2. Abre **Configuración → Turnos online**, comprueba que el enlace y el QR
   empiecen por `https://` y escanea el QR desde el móvil.
3. Exporta un reporte a PDF (verifica `gd` y los permisos de `storage/`).
4. Sube una imagen en **Imagenología** (verifica `storage:link` y los límites
   de subida).
5. Agenda una cita y confirma que llega el correo.

---

## 11. Actualizar

```bash
cd /var/www/odontosuite
php artisan down

git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan up
```

---

## 12. Copias de seguridad

Lo que hay que respaldar es la base de datos y los archivos subidos:

```bash
# Base de datos
pg_dump -U odontosuite odontosuite | gzip > respaldo-$(date +%F).sql.gz

# Radiografías, fotos y logotipos
tar czf archivos-$(date +%F).tar.gz -C /var/www/odontosuite storage/app/public
```

Guarda también una copia de `.env`: sin `APP_KEY` no se pueden descifrar las
sesiones ni los datos cifrados.

---

## Problemas frecuentes

| Síntoma | Causa habitual |
|---|---|
| Error 500 en todas las páginas | La base no está migrada: el sistema lee los ajustes de la clínica en cada petición. Corre `php artisan migrate --force`. |
| Cambié `.env` y no pasa nada | La configuración está cacheada. Vuelve a correr `php artisan config:cache`. |
| 419 «Página expirada» al enviar un formulario | La sesión no se guarda: revisa que la tabla `sessions` exista y que `storage/` sea escribible. |
| Las imágenes subidas dan 404 | Falta `php artisan storage:link`. |
| El sitio se ve sin estilos | Falta `npm run build`, o `public/build` no se subió al servidor. |
| El QR de reservas apunta a `http://` | Hay un proxy TLS delante: define `TRUSTED_PROXIES` y rehaz `config:cache`. |
| «Permission denied» al escribir logs | `storage/` y `bootstrap/cache/` no pertenecen a `www-data`. Repite el paso 7. |
| Las subidas grandes fallan | Sube `client_max_body_size` en nginx y `upload_max_filesize` / `post_max_size` en PHP. |
