#!/usr/bin/env bash
# ==============================================================================
# deploy/setup.sh — PRIMERA puesta en marcha del sitio en el VPS.
#
# Hace todo lo que normalmente se hace a mano:
#   · commitea y pushea lo que tengas suelto (mensaje automático)
#   · clona la rama main del repo en /var/www/paqueteria
#   · crea la base MySQL y su usuario (contraseña generada sola)
#   · escribe el .env de producción y genera APP_KEY
#   · composer install --no-dev
#   · compila los assets acá y sube 130 KB
#   · migra, siembra y vuelca el .sql inicial (lo único pesado que viaja, y
#     una sola vez: tools/ está gitignoreado, no puede venir del repo)
#   · deja el virtual host de nginx andando + certificado SSL con certbot
#   · cron del scheduler y worker de colas por systemd
#
# Es idempotente: se puede volver a correr sin miedo.
#
#   ./setup.sh                 puesta en marcha completa
#   ./setup.sh --sin-ssl       sin pedir certificado (DNS todavía sin propagar)
#   ./setup.sh --sin-datos     sin volcar el .sql inicial
#   ./setup.sh --forzar-datos  re-siembra y re-importa aunque ya haya datos
#   ./setup.sh --forzar-nginx  regenera el vhost aunque certbot lo haya tocado
#   ./setup.sh --sin-commit    no commitea ni pushea nada
#   ./setup.sh --si            no pregunta nada
# ==============================================================================
set -euo pipefail

AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RAIZ_REPO="$(cd "$AQUI/.." && pwd)"
# shellcheck source=/dev/null
. "$AQUI/lib.sh"

OMITIR_ASSETS=0; OMITIR_SSL=0; OMITIR_DATOS=0; FORZAR_DATOS=0; FORZAR_NGINX=0

while [ $# -gt 0 ]; do
    case "$1" in
        --sin-assets)    OMITIR_ASSETS=1 ;;
        --sin-ssl)       OMITIR_SSL=1 ;;
        --sin-datos)     OMITIR_DATOS=1 ;;
        --forzar-datos)  FORZAR_DATOS=1 ;;
        --forzar-nginx)  FORZAR_NGINX=1 ;;
        --sin-commit)    AUTO_COMMIT=0 ;;
        --si|-y)         AUTO=1 ;;
        -h|--ayuda|--help) awk 'NR>1 && /^#/ {sub(/^# ?/,""); print; next} NR>1 {exit}' "${BASH_SOURCE[0]}"; exit 0 ;;
        *) morir "opción desconocida: $1 (probá --ayuda)" ;;
    esac
    shift
done

cargar_config "$AQUI"
verificar_comandos_locales ssh scp git tar gzip

# ------------------------------------------------------------------- resumen
cat <<RESUMEN

  ${C_BOLD}Puesta en marcha de $APP_NAME${C_RESET}

    Servidor  : $SSH_HOST
    Dominio   : $DOMINIO${DOMINIOS_ALIAS:+ (+ $DOMINIOS_ALIAS)}
    Carpeta   : $SITE_DIR   (Laravel en $SITE_DIR/$APP_SUBDIR)
    Repo      : $REPO_URL  rama $REPO_BRANCH
    Base      : $DB_NAME / usuario $DB_USER
    PHP       : $PHP_BIN vía $PHP_FPM_PASS
    Assets    : $([ "$OMITIR_ASSETS" = 1 ] && echo "no se tocan" || echo "compilados desde $ASSETS_ORIGEN")
    Datos     : $([ "$OMITIR_DATOS" = 1 ] && echo "sin volcado inicial" || echo "${SQL_INICIAL:-sin volcado inicial}")
    SSL       : $([ "$SSL" = 1 ] && [ "$OMITIR_SSL" = 0 ] && echo "certbot ($SSL_EMAIL)" || echo "no")

RESUMEN

confirmar "¿Arranco?" || { info "cancelado"; exit 0; }

verificar_ssh

# ----------------------------------------------------------------- commit
paso "Commiteando y pusheando"
auto_commit
verificar_sincronizado

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

# ------------------------------------------------------------------ assets
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

# ---------------------------------------------------------- volcado inicial
if [ "$OMITIR_DATOS" = "0" ] && [ -n "${SQL_INICIAL:-}" ]; then
    paso "Subiendo el volcado inicial"
    SQL_PATH="$RAIZ_REPO/$SQL_INICIAL"
    [ -f "$SQL_PATH" ] || morir "no encuentro el .sql inicial: $SQL_PATH"
    gzip -c "$SQL_PATH" > "$TMP/datos.sql.gz"
    info "$(basename "$SQL_PATH") → $(du -h "$TMP/datos.sql.gz" | cut -f1) comprimido"
    subir "$TMP/datos.sql.gz" "$SSH_HOST:$SQL_REMOTO"
    ok "$SQL_REMOTO"
else
    remoto "rm -f '$SQL_REMOTO'"
fi

# ------------------------------------------------------------------ ejecución
paso "Instalando en $SSH_HOST"
{
    echo 'set -e'
    exportaciones_remotas
    printf 'export OMITIR_ASSETS=%s OMITIR_SSL=%s OMITIR_DATOS=%s FORZAR_DATOS=%s FORZAR_NGINX=%s\n' \
        "$OMITIR_ASSETS" "$OMITIR_SSL" "$OMITIR_DATOS" "$FORZAR_DATOS" "$FORZAR_NGINX"
    cat <<'ARRANQUE'

# El repo trae consigo los scripts y las plantillas: lo primero es tenerlo.
mkdir -p "$(dirname "$SITE_DIR")"
git config --global --add safe.directory "$SITE_DIR" 2>/dev/null || true

if [ -d "$SITE_DIR/.git" ]; then
    git -C "$SITE_DIR" remote set-url origin "$REPO_URL"
    git -C "$SITE_DIR" fetch --prune --quiet origin "$REPO_BRANCH"
    git -C "$SITE_DIR" checkout -B "$REPO_BRANCH" "origin/$REPO_BRANCH" --quiet
    git -C "$SITE_DIR" reset --hard --quiet "origin/$REPO_BRANCH"
    echo "      ✓ repo actualizado a $(git -C "$SITE_DIR" rev-parse --short HEAD)"
else
    if [ -e "$SITE_DIR" ] && [ -n "$(ls -A "$SITE_DIR" 2>/dev/null)" ]; then
        echo "✗ [vps] $SITE_DIR existe, tiene contenido y no es un repo git. Moverlo o borrarlo a mano." >&2
        exit 1
    fi
    if ! git clone --branch "$REPO_BRANCH" "$REPO_URL" "$SITE_DIR" --quiet; then
        echo "✗ [vps] no pude clonar. ¿La clave del VPS está autorizada en GitHub? Probá: ssh -T git@github.com" >&2
        exit 1
    fi
    echo "      ✓ repo clonado en $SITE_DIR ($(git -C "$SITE_DIR" rev-parse --short HEAD))"
fi

GUION="$SITE_DIR/deploy/remote/setup-remote.sh"
if [ ! -f "$GUION" ]; then
    echo "✗ [vps] $REPO_BRANCH no trae deploy/remote/. ¿Está versionada la carpeta deploy/?" >&2
    exit 1
fi
exec bash "$GUION"
ARRANQUE
} > "$TMP/arranque.sh"

ejecutar_remoto "$TMP/arranque.sh"

cat <<FIN

  ${C_GREEN}${C_BOLD}Sitio instalado.${C_RESET}

  Entrá con los usuarios del seeder (ver credenciales/usuarios.md):
    it.codeland@gmail.com                    superusuario
    admin@transporteelisa.com.ar             admin
    operador.goya@transporteelisa.com.ar     operador

  ${C_YELLOW}Cambiá esas contraseñas apenas entres:${C_RESET} están en un archivo que
  estuvo versionado, así que siguen siendo recuperables de la historia del repo.

  De acá en más, para actualizar: ${C_BOLD}./deploy.sh${C_RESET}

FIN
