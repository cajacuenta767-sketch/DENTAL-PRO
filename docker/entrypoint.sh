#!/usr/bin/env bash
# Entrypoint de OdontoSuite.
#
# - Espera a que la base de datos acepte conexiones.
# - Ejecuta migraciones y enlaza storage sólo en el contenedor "app"
#   (o cuando RUN_MIGRATIONS=true), para que los workers de cola y el
#   scheduler no compitan por hacerlo.
# - Cachea configuración, rutas y vistas en producción.

set -euo pipefail

DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-5432}"
DB_USERNAME="${DB_USERNAME:-postgres}"
DB_DATABASE="${DB_DATABASE:-odontosuite}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-false}"
WAIT_DB_TIMEOUT="${WAIT_DB_TIMEOUT:-60}"

esperar_bd() {
    echo "[entrypoint] Esperando a PostgreSQL en ${DB_HOST}:${DB_PORT}..."
    local intentos=0
    until PGPASSWORD="${DB_PASSWORD:-}" pg_isready -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" -q; do
        intentos=$((intentos + 1))
        if [ "$intentos" -ge "$WAIT_DB_TIMEOUT" ]; then
            echo "[entrypoint] La base de datos no respondió tras ${WAIT_DB_TIMEOUT}s. Abortando." >&2
            exit 1
        fi
        sleep 1
    done
    echo "[entrypoint] PostgreSQL disponible."
}

if [ "${DB_CONNECTION:-pgsql}" = "pgsql" ]; then
    esperar_bd
fi

if [ -z "${APP_KEY:-}" ] && [ "${APP_ENV:-production}" = "production" ]; then
    echo "[entrypoint] ERROR: APP_KEY está vacío. Genera uno con: php artisan key:generate --show" >&2
    exit 1
elif [ -z "${APP_KEY:-}" ]; then
    echo "[entrypoint] AVISO: APP_KEY vacío." >&2
fi

if [ "$RUN_MIGRATIONS" = "true" ]; then
    # Los volúmenes Docker conservan propietario entre despliegues. Aseguramos
    # que Apache y los workers puedan crear fotos, estudios, firmas y respaldos.
    echo "[entrypoint] Preparando permisos de almacenamiento..."
    mkdir -p storage/app/private storage/app/public storage/logs
    chown -R www-data:www-data storage/app storage/logs
    chmod -R u+rwX,g+rwX storage/app storage/logs

    echo "[entrypoint] Ejecutando migraciones..."
    php artisan migrate --force

    if [ ! -L public/storage ]; then
        echo "[entrypoint] Creando enlace simbólico public/storage..."
        php artisan storage:link --force || true
    fi
fi

if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
