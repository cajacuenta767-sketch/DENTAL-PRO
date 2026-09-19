#!/usr/bin/env bash
#
# Instalación de OdontoSuite de punta a punta.
#
#   ./scripts/instalar.sh              clínica demo cargada (50 pacientes, 200 citas…)
#   ./scripts/instalar.sh --en-blanco  solo roles, permisos, ajustes y cuentas
#   ./scripts/instalar.sh --sin-node   omite npm (la app funciona, pero sin estilos)
#
# Es idempotente: se puede volver a correr sobre una instalación existente.
# No instala paquetes del sistema ni toca PostgreSQL más allá de crear la base;
# si algo falta, lo dice y para. Para un servidor, sigue docs/DESPLIEGUE.md.

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

SEMILLA=demo
USAR_NODE=1

for arg in "$@"; do
    case "$arg" in
        --en-blanco) SEMILLA=blanco ;;
        --sin-node)  USAR_NODE=0 ;;
        -h|--help)   sed -n '2,12p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Opción desconocida: $arg (usa --help)" >&2; exit 2 ;;
    esac
done

paso()  { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }
ok()    { printf '    \033[32m✓\033[0m %s\n' "$1"; }
aviso() { printf '    \033[33m!\033[0m %s\n' "$1"; }
morir() { printf '\n\033[1;31m✗ %s\033[0m\n' "$1" >&2; exit 1; }

# --- 1. Requisitos ----------------------------------------------------------

paso "Comprobando requisitos"

command -v php >/dev/null || morir "Falta PHP 8.2+. Instálalo y vuelve a correr esto."
command -v composer >/dev/null || morir "Falta Composer 2. Ver https://getcomposer.org"

PHP_OK=$(php -r 'echo PHP_VERSION_ID >= 80200 ? 1 : 0;')
[ "$PHP_OK" = "1" ] || morir "PHP $(php -r 'echo PHP_VERSION;') es muy viejo; hace falta 8.2 o superior."
ok "PHP $(php -r 'echo PHP_VERSION;')"

FALTAN=""
for ext in pdo_pgsql mbstring gd zip intl; do
    php -m | grep -qi "^${ext}$" || FALTAN="$FALTAN $ext"
done
[ -z "$FALTAN" ] || morir "Faltan extensiones de PHP:$FALTAN
  Debian/Ubuntu: sudo apt install$(printf ' php-%s' $FALTAN)"
ok "Extensiones pdo_pgsql, mbstring, gd, zip, intl"

command -v psql >/dev/null || morir "Falta el cliente de PostgreSQL (psql)."

if [ "$USAR_NODE" = "1" ]; then
    command -v npm >/dev/null || morir "Falta Node.js 20+ y npm. Usa --sin-node para omitir el build."
    ok "Node $(node -v)"
fi

# --- 2. Dependencias PHP ----------------------------------------------------

paso "Instalando dependencias de PHP"
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-interaction --no-progress
ok "vendor/ listo"

# --- 3. Entorno -------------------------------------------------------------

paso "Preparando .env"
if [ -f .env ]; then
    ok ".env ya existe, se respeta tal cual"
else
    cp .env.example .env
    ok ".env creado desde .env.example"
fi

if grep -q '^APP_KEY=$' .env; then
    php artisan key:generate --ansi
else
    ok "APP_KEY ya definida"
fi

# Lee la configuración de base de datos del .env, sin exportarla al entorno
# (si la exportáramos, pisaría al .env en los artisan de más abajo).
leer_env() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | tr -d '"' | tr -d "'"; }

DB_NOMBRE=$(leer_env DB_DATABASE); DB_NOMBRE=${DB_NOMBRE:-odontosuite}
DB_USUARIO=$(leer_env DB_USERNAME); DB_USUARIO=${DB_USUARIO:-postgres}
DB_HOST=$(leer_env DB_HOST); DB_HOST=${DB_HOST:-127.0.0.1}
DB_PUERTO=$(leer_env DB_PORT); DB_PUERTO=${DB_PUERTO:-5432}
DB_CLAVE=$(leer_env DB_PASSWORD)

# --- 4. Base de datos -------------------------------------------------------

paso "Comprobando PostgreSQL en $DB_HOST:$DB_PUERTO"

export PGPASSWORD="$DB_CLAVE"
psql -h "$DB_HOST" -p "$DB_PUERTO" -U "$DB_USUARIO" -d postgres -c '\q' 2>/dev/null || morir \
"No se pudo conectar a PostgreSQL como «$DB_USUARIO».

  · ¿Está corriendo?   sudo service postgresql start
  · ¿Existe el usuario y la contraseña coincide con DB_PASSWORD de .env?
      sudo -u postgres psql -c \"ALTER USER $DB_USUARIO PASSWORD 'tu_clave';\""
ok "Conexión establecida"

existe_base() {
    psql -h "$DB_HOST" -p "$DB_PUERTO" -U "$DB_USUARIO" -d postgres -tAc \
        "SELECT 1 FROM pg_database WHERE datname='$1'" 2>/dev/null | grep -q 1
}

for base in "$DB_NOMBRE" "${DB_NOMBRE}_testing"; do
    if existe_base "$base"; then
        ok "Base «$base» ya existe"
    else
        createdb -h "$DB_HOST" -p "$DB_PUERTO" -U "$DB_USUARIO" "$base"
        ok "Base «$base» creada"
    fi
done
unset PGPASSWORD

# --- 5. Migrar y sembrar ----------------------------------------------------

paso "Migrando la base de datos"
php artisan migrate --force --ansi

paso "Sembrando datos"

# Los seeders no son idempotentes: crean citas con un índice único por doctor
# y hora, así que correrlos dos veces revienta. Solo se siembra una base vacía.
YA_SEMBRADA=$(php artisan tinker --execute='
    try { echo \App\Models\Usuario::count() > 0 ? "si" : "no"; }
    catch (\Throwable $e) { echo "no"; }
' 2>/dev/null | tr -d '\r\n ' | grep -oE '(si|no)$' | tail -1)

if [ "${YA_SEMBRADA:-no}" = "si" ]; then
    aviso "La base ya tiene datos: no se vuelve a sembrar."
    aviso "Para empezar de cero: php artisan migrate:fresh --seed --force"
elif [ "$SEMILLA" = "demo" ]; then
    php artisan db:seed --force --ansi
else
    for s in AjusteSeeder RolPermisoSeeder UsuarioSeeder; do
        php artisan db:seed --class="$s" --force --ansi
    done
    aviso "Base en blanco: sin pacientes, citas ni catálogo."
fi

# --- 6. Archivos y assets ---------------------------------------------------

paso "Enlazando storage"
php artisan storage:link --ansi || ok "El enlace ya existía"

if [ "$USAR_NODE" = "1" ]; then
    paso "Compilando los assets"
    if [ -f package-lock.json ]; then npm ci --no-audit --no-fund; else npm install --no-audit --no-fund; fi
    npm run build
    ok "public/build generado"
else
    aviso "Se omitió el build: el panel se verá sin estilos hasta correr «npm run build»."
fi

# --- 7. Comprobación --------------------------------------------------------

paso "Comprobando la instalación"
php artisan optimize:clear --ansi >/dev/null
./scripts/verificar.sh

cat <<FIN

  Listo. Levanta el servidor con:

      php artisan serve

  y abre http://localhost:8000

FIN

if [ "$SEMILLA" = "demo" ]; then
cat <<'FIN'
  Cuentas de demostración (cámbialas antes de publicar el sistema):

      admin@admin.com               admin123        Super administrador
      admin@clinica.com             admin123        Administrador
      secretaria@clinica.com        recepcion123    Recepción
      sofia.arancibia@clinica.com   doctor123       Doctor

FIN
fi
