#!/usr/bin/env bash
# ==============================================================================
# deploy/artisan.sh — corre un comando artisan en el VPS.
#
#   ./artisan.sh migrate:status
#   ./artisan.sh tinker            (interactivo)
#   ./artisan.sh cache:clear
#
# Sin argumentos abre una shell en la carpeta de la app.
# ==============================================================================
set -euo pipefail

AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RAIZ_REPO="$(cd "$AQUI/.." && pwd)"
# shellcheck source=/dev/null
. "$AQUI/lib.sh"
cargar_config "$AQUI"

APP_DIR="$SITE_DIR/$APP_SUBDIR"

if [ $# -eq 0 ]; then
    # shellcheck disable=SC2086
    exec ssh $SSH_OPTS -t "$SSH_HOST" "cd '$APP_DIR' && exec bash -l"
fi

# Comillas por argumento, para que 'db:seed --class=UserSeeder' llegue entero.
CMD=""
for a in "$@"; do CMD="$CMD $(printf '%q' "$a")"; done

# shellcheck disable=SC2086,SC2029
ssh $SSH_OPTS -t "$SSH_HOST" "cd '$APP_DIR' && $PHP_BIN artisan$CMD; \
    chown -R $WEB_USER:$WEB_GROUP '$APP_DIR/storage' '$APP_DIR/bootstrap/cache'"
