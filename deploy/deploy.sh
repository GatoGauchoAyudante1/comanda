#!/usr/bin/env bash
# ==============================================================================
# deploy/deploy.sh — ACTUALIZAR el sitio ya instalado.
#
#   · commitea y pushea solo lo que tengas sin commitear (mensaje automático)
#   · compila los assets acá y sube 130 KB por scp — es lo único que viaja
#   · en el VPS: git reset --hard origin/main y corre deploy/remote/deploy-remote.sh,
#     que llega con el propio repo (deploy/ está versionado)
#   · composer install sólo si cambió composer.lock
#   · no toca .env, ni nginx, ni el certificado, ni los datos
#
#   ./deploy.sh                     deploy completo
#   ./deploy.sh --sin-assets        no recompila ni sube assets (sólo código)
#   ./deploy.sh --sin-migraciones   no corre migrate
#   ./deploy.sh --sin-mantenimiento no baja el sitio mientras despliega
#   ./deploy.sh --sin-commit        no commitea ni pushea nada
#   ./deploy.sh --con-composer      fuerza composer install aunque no cambie el lock
#   ./deploy.sh --si                no pregunta nada
# ==============================================================================
set -euo pipefail

AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RAIZ_REPO="$(cd "$AQUI/.." && pwd)"
# shellcheck source=/dev/null
. "$AQUI/lib.sh"

OMITIR_ASSETS=0; OMITIR_MIGRACIONES=0; FORZAR_COMPOSER=0

while [ $# -gt 0 ]; do
    case "$1" in
        --sin-assets)        OMITIR_ASSETS=1 ;;
        --sin-migraciones)   OMITIR_MIGRACIONES=1 ;;
        --sin-mantenimiento) MANTENIMIENTO=0 ;;
        --sin-commit)        AUTO_COMMIT=0 ;;
        --con-composer)      FORZAR_COMPOSER=1 ;;
        --si|-y)             AUTO=1 ;;
        -h|--ayuda|--help)   awk 'NR>1 && /^#/ {sub(/^# ?/,""); print; next} NR>1 {exit}' "${BASH_SOURCE[0]}"; exit 0 ;;
        *) morir "opción desconocida: $1 (probá --ayuda)" ;;
    esac
    shift
done

# config.sh usa ${VAR:-default}, así que lo que fijaron los flags le gana.
cargar_config "$AQUI"
verificar_comandos_locales ssh scp git tar

echo
printf '  %sDeploy de %s%s  →  %s (%s)\n' \
    "$C_BOLD" "$APP_NAME" "$C_RESET" "$DOMINIO" "$SSH_HOST"

verificar_ssh
confirmar "¿Despliego $REPO_BRANCH?" || { info "cancelado"; exit 0; }

# ----------------------------------------------------------------- commit
paso "Commiteando y pusheando"
auto_commit
verificar_sincronizado

# ----------------------------------------------------------------- assets
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

if [ "$OMITIR_ASSETS" = "0" ]; then
    paso "Compilando assets"
    compilar_assets "$TMP/build.tar.gz"
    paso "Subiendo assets"
    subir "$TMP/build.tar.gz" "$SSH_HOST:$ASSETS_REMOTO"
    ok "$ASSETS_REMOTO"
else
    aviso "assets salteados (--sin-assets)"
    remoto "rm -f '$ASSETS_REMOTO'"
fi

# ------------------------------------------------------------- ejecución
paso "Desplegando en $SSH_HOST"
{
    echo 'set -e'
    exportaciones_remotas
    printf 'export OMITIR_ASSETS=%s OMITIR_MIGRACIONES=%s FORZAR_COMPOSER=%s\n' \
        "$OMITIR_ASSETS" "$OMITIR_MIGRACIONES" "$FORZAR_COMPOSER"
    cat <<'ARRANQUE'

APP_DIR="$SITE_DIR/$APP_SUBDIR"

if [ ! -d "$SITE_DIR/.git" ]; then
    echo "✗ [vps] $SITE_DIR no es un repo git. Corré primero deploy/setup.sh" >&2
    exit 1
fi
git config --global --add safe.directory "$SITE_DIR" 2>/dev/null || true

# Referencias del estado ANTERIOR: el script de deploy las usa para saber si
# cambió composer.lock y para mostrar el rango de commits.
export COMMIT_ANTERIOR="$(git -C "$SITE_DIR" rev-parse --short HEAD)"
export LOCK_ANTES="$(md5sum "$APP_DIR/composer.lock" 2>/dev/null | cut -d' ' -f1)"

# Mantenimiento ANTES de mover el código: entre el reset y el composer install
# el árbol puede quedar inconsistente.
if [ "${MANTENIMIENTO:-1}" = "1" ] && [ -f "$APP_DIR/vendor/autoload.php" ]; then
    ( cd "$APP_DIR" && "$PHP_BIN" artisan down --retry=15 ) >/dev/null 2>&1 || true
fi

git -C "$SITE_DIR" remote set-url origin "$REPO_URL"
git -C "$SITE_DIR" fetch --prune --quiet origin "$REPO_BRANCH"
git -C "$SITE_DIR" reset --hard --quiet "origin/$REPO_BRANCH"

GUION="$SITE_DIR/deploy/remote/deploy-remote.sh"
if [ ! -f "$GUION" ]; then
    echo "✗ [vps] $REPO_BRANCH no trae deploy/remote/. ¿Está versionada la carpeta deploy/?" >&2
    exit 1
fi
exec bash "$GUION"
ARRANQUE
} > "$TMP/arranque.sh"

ejecutar_remoto "$TMP/arranque.sh"
echo
