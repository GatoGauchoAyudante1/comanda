#!/usr/bin/env bash
# ==============================================================================
# deploy/backup-db.sh — mysqldump de la base de producción y descarga.
#
#   ./backup-db.sh              deja el .sql.gz en deploy/backups/
#   ./backup-db.sh --solo-vps   lo genera en el VPS y no lo baja
#
# Conviene correrlo antes de un deploy con migraciones pesadas.
# ==============================================================================
set -euo pipefail

AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RAIZ_REPO="$(cd "$AQUI/.." && pwd)"
# shellcheck source=/dev/null
. "$AQUI/lib.sh"

SOLO_VPS=0
if [ "${1:-}" = "--solo-vps" ]; then SOLO_VPS=1; fi

cargar_config "$AQUI"
verificar_comandos_locales ssh scp

SELLO="$(date +%Y%m%d-%H%M%S)"
REMOTO_DIR="/var/www/backups/paqueteria"
ARCHIVO="$DB_NAME-$SELLO.sql.gz"

paso "Volcando $DB_NAME en el VPS"
remoto "mkdir -p '$REMOTO_DIR' && \
    mysqldump --protocol=socket -uroot --single-transaction --quick \
        --default-character-set=utf8mb4 --routines --events '$DB_NAME' \
    | gzip > '$REMOTO_DIR/$ARCHIVO' && \
    ls -lh '$REMOTO_DIR/$ARCHIVO'"
ok "generado en $REMOTO_DIR/$ARCHIVO"

if [ "$SOLO_VPS" = "1" ]; then
    exit 0
fi

paso "Descargando"
mkdir -p "$AQUI/backups"
subir "$SSH_HOST:$REMOTO_DIR/$ARCHIVO" "$AQUI/backups/"
ok "deploy/backups/$ARCHIVO"
