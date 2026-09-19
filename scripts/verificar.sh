#!/usr/bin/env bash
#
# Comprueba que la instalación está completa y puede funcionar.
# Sale con código 1 si algo falta, para poder usarlo en CI o tras un despliegue.
#
#   ./scripts/verificar.sh

set -uo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

FALLOS=0
ok()   { printf '    \033[32m✓\033[0m %s\n' "$1"; }
mal()  { printf '    \033[31m✗\033[0m %s\n' "$1"; FALLOS=$((FALLOS+1)); }
tibio(){ printf '    \033[33m!\033[0m %s\n' "$1"; }

printf '\n\033[1mComprobando la instalación\033[0m\n'

# --- Archivos base ----------------------------------------------------------

[ -d vendor ] && ok "Dependencias de PHP instaladas" || mal "Falta vendor/ · corre «composer install»"
[ -f .env ]   && ok ".env presente" || mal "Falta .env · corre «cp .env.example .env»"

if [ -f .env ] && ! grep -q '^APP_KEY=$' .env; then
    ok "APP_KEY definida"
else
    mal "APP_KEY vacía · corre «php artisan key:generate»"
fi

# --- Base de datos ----------------------------------------------------------

if [ -d vendor ]; then
    if php artisan db:show >/dev/null 2>&1; then
        ok "Conexión a la base de datos"

        PENDIENTES=$(php artisan migrate:status 2>/dev/null | grep -c "Pending" || true)
        if [ "${PENDIENTES:-0}" -eq 0 ]; then
            ok "Migraciones al día"
        else
            mal "$PENDIENTES migraciones pendientes · corre «php artisan migrate --force»"
        fi

        # Los permisos y los ajustes son lo mínimo para que el panel abra.
        CUENTA=$(php artisan tinker --execute='
            try {
                echo \Spatie\Permission\Models\Permission::count().":"
                    .\Spatie\Permission\Models\Role::count().":"
                    .\App\Models\Usuario::count().":"
                    .(\App\Models\Ajuste::query()->exists() ? 1 : 0);
            } catch (\Throwable $e) { echo "0:0:0:0"; }
        ' 2>/dev/null | tr -d '\r\n ' | grep -oE '[0-9]+:[0-9]+:[0-9]+:[0-9]+' | tail -1)

        IFS=: read -r PERMISOS ROLES USUARIOS AJUSTES <<< "${CUENTA:-0:0:0:0}"

        [ "${PERMISOS:-0}" -gt 0 ] && ok "$PERMISOS permisos sembrados" \
            || mal "Sin permisos · corre «php artisan db:seed --class=RolPermisoSeeder --force»"
        [ "${ROLES:-0}" -gt 0 ] && ok "$ROLES roles sembrados" \
            || mal "Sin roles · corre «php artisan db:seed --class=RolPermisoSeeder --force»"
        [ "${USUARIOS:-0}" -gt 0 ] && ok "$USUARIOS cuentas de acceso" \
            || mal "Sin usuarios · corre «php artisan db:seed --class=UsuarioSeeder --force»"
        [ "${AJUSTES:-0}" = "1" ] && ok "Ajustes de la clínica cargados" \
            || mal "Sin ajustes · corre «php artisan db:seed --class=AjusteSeeder --force»"
    else
        mal "No se pudo conectar a la base de datos · revisa DB_* en .env y que PostgreSQL esté corriendo"
    fi
fi

# --- Archivos subidos y assets ---------------------------------------------

[ -L public/storage ] && ok "Enlace de storage" || mal "Falta el enlace · corre «php artisan storage:link»"

for d in storage/framework storage/logs bootstrap/cache; do
    [ -w "$d" ] && ok "Escritura en $d" || mal "$d no es escribible · revisa los permisos"
done

if [ -f public/build/manifest.json ]; then
    ok "Assets compilados"
else
    tibio "Sin assets · el panel se verá sin estilos hasta correr «npm run build»"
fi

# --- Idioma -----------------------------------------------------------------
# Sin lang/es, Laravel muestra la clave cruda («validation.required») en cada
# formulario en vez del mensaje. No falla, solo se ve mal: por eso se comprueba.

if [ -f lang/es/validation.php ] && [ -f lang/es/passwords.php ] \
   && [ -f lang/es/pagination.php ] && [ -f lang/es/auth.php ]; then
    ok "Traducciones al español presentes"
else
    mal "Falta lang/es · los formularios mostrarían «validation.required» en pantalla"
fi

# --- Resultado --------------------------------------------------------------

echo
if [ "$FALLOS" -eq 0 ]; then
    printf '\033[1;32m  Todo en orden.\033[0m\n\n'
    exit 0
fi

printf '\033[1;31m  %s comprobación(es) fallaron.\033[0m\n\n' "$FALLOS"
exit 1
