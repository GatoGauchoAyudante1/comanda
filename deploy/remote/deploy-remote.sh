#!/usr/bin/env bash
# ==============================================================================
# deploy-remote.sh — se ejecuta DENTRO del VPS, desde el propio checkout
# (/var/www/paqueteria/deploy/remote/). Llega con el repo, no por scp.
#
# Cuando arranca, deploy.sh ya dejó hecho, en este orden:
#   · artisan down            (si MANTENIMIENTO=1)
#   · git reset --hard origin/main
#   · exportó COMMIT_ANTERIOR y LOCK_ANTES (estado previo al reset)
#
# Acá va el resto: dependencias, assets, migraciones, cachés y servicios.
# No toca .env, ni nginx, ni el certificado, ni los datos.
# ==============================================================================
set -euo pipefail

AQUI="$(cd "$(dirname "$0")" && pwd)"
DEPLOY_DIR="$(cd "$AQUI/.." && pwd)"
# shellcheck source=/dev/null
. "$DEPLOY_DIR/config.sh"
# shellcheck source=/dev/null
. "$AQUI/_comun.sh"
# Ya las leímos: fuera del entorno, para que artisan no las confunda con el .env.
desexportar_config

APP_DIR="$SITE_DIR/$APP_SUBDIR"
ROOT_PUBLICO="$APP_DIR/public"
ENV_FILE="$APP_DIR/.env"

OMITIR_ASSETS="${OMITIR_ASSETS:-0}"
OMITIR_MIGRACIONES="${OMITIR_MIGRACIONES:-0}"
FORZAR_COMPOSER="${FORZAR_COMPOSER:-0}"
COMMIT_ANTERIOR="${COMMIT_ANTERIOR:-}"
LOCK_ANTES="${LOCK_ANTES:-}"

[ -f "$ENV_FILE" ] || morir "no existe $ENV_FILE. Corré primero deploy/setup.sh"

# El sitio ya quedó en mantenimiento antes del reset: pase lo que pase, sale.
BAJADO="${MANTENIMIENTO:-1}"
levantar() {
    if [ "${BAJADO:-0}" = "1" ]; then
        ( cd "$APP_DIR" && "$PHP_BIN" artisan up >/dev/null 2>&1 ) || true
        BAJADO=0
    fi
}
trap levantar EXIT

# ------------------------------------------------------------------ 1. código
paso "Código"
COMMIT_NUEVO="$(git -C "$SITE_DIR" rev-parse --short HEAD)"
if [ -z "$COMMIT_ANTERIOR" ] || [ "$COMMIT_ANTERIOR" = "$COMMIT_NUEVO" ]; then
    info "sin cambios de código ($COMMIT_NUEVO) — igual refresco assets y cachés"
else
    ok "$COMMIT_ANTERIOR → $COMMIT_NUEVO"
    git -C "$SITE_DIR" log --oneline "$COMMIT_ANTERIOR..$COMMIT_NUEVO" 2>/dev/null \
        | head -10 | sed 's/^/        /' || true
fi

# ------------------------------------------------------------ 2. dependencias
paso "Dependencias PHP"
LOCK_AHORA="$(md5sum "$APP_DIR/composer.lock" 2>/dev/null | cut -d' ' -f1 || true)"
if [ "$FORZAR_COMPOSER" = "1" ]; then
    info "forzado por --con-composer"
    componer; ok "vendor/ reinstalado"
elif [ ! -f "$APP_DIR/vendor/autoload.php" ]; then
    info "no hay vendor/"
    componer; ok "vendor/ instalado"
elif [ "$LOCK_ANTES" != "$LOCK_AHORA" ]; then
    info "composer.lock cambió"
    componer; ok "vendor/ actualizado"
else
    ok "composer.lock igual: no reinstalo nada"
fi

# ------------------------------------------------------------------ 3. assets
paso "Assets compilados"
if [ "$OMITIR_ASSETS" = "1" ]; then
    aviso "salteado por --sin-assets"
elif [ -f "$ASSETS_REMOTO" ]; then
    rm -rf "$ROOT_PUBLICO/build"
    tar -xzf "$ASSETS_REMOTO" -C "$ROOT_PUBLICO"
    [ -f "$ROOT_PUBLICO/build/manifest.json" ] || morir "el paquete de assets no trae manifest.json"
    rm -f "$ASSETS_REMOTO"
    ok "public/build actualizado"
else
    aviso "no llegó $ASSETS_REMOTO: dejo los assets que ya estaban"
fi

# -------------------------------------------------------------- 4. migraciones
paso "Migraciones"
if [ "$OMITIR_MIGRACIONES" = "1" ]; then
    aviso "salteado por --sin-migraciones"
else
    artisan migrate --force --no-interaction
    ok "base al día"
fi

# ------------------------------------------------------------------ 5. cachés
paso "Cachés"
artisan optimize:clear >/dev/null
artisan config:cache >/dev/null
artisan route:cache  >/dev/null
artisan view:cache   >/dev/null
artisan event:cache  >/dev/null 2>&1 || true
ok "config, rutas y vistas cacheadas"

# --------------------------------------------------------------- 6. servicios
paso "Permisos y servicios"
arreglar_permisos
systemctl reload "$PHP_FPM_SERVICE" || true
artisan queue:restart >/dev/null 2>&1 || true
if systemctl list-unit-files | grep -q "^$SERVICIO_QUEUE.service"; then
    systemctl restart "$SERVICIO_QUEUE" || true
fi
ok "PHP-FPM recargado y worker reiniciado"

# --------------------------------------------------------- 7. arriba de nuevo
levantar
trap - EXIT

paso "Chequeo final"
chequear_sitio

echo
echo "  ${R_GREEN}${R_BOLD}Deploy terminado.${R_RESET}  $(env_leer APP_URL) — commit $COMMIT_NUEVO"
