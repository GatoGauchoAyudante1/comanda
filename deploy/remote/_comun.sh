# ==============================================================================
# deploy/remote/_comun.sh — helpers que corren DENTRO del VPS.
# Lo cargan setup-remote.sh y deploy-remote.sh con `source`.
# ==============================================================================

if [ -t 1 ]; then
    R_RESET=$'\033[0m'; R_BOLD=$'\033[1m'; R_RED=$'\033[31m'
    R_GREEN=$'\033[32m'; R_YELLOW=$'\033[33m'; R_BLUE=$'\033[34m'
else
    R_RESET=; R_BOLD=; R_RED=; R_GREEN=; R_YELLOW=; R_BLUE=
fi

paso()  { printf '\n%s [vps] ==>%s %s%s%s\n' "$R_BLUE" "$R_RESET" "$R_BOLD" "$*" "$R_RESET"; }
info()  { printf '      %s\n' "$*"; }
ok()    { printf '      %s✓%s %s\n' "$R_GREEN" "$R_RESET" "$*"; }
aviso() { printf '      %s!%s %s\n' "$R_YELLOW" "$R_RESET" "$*"; }
morir() { printf '\n%s✗ [vps] %s%s\n' "$R_RED" "$*" "$R_RESET" >&2; exit 1; }

# render <plantilla> <salida> VAR1 VAR2 ... — reemplaza {{VAR}} por su valor.
render() {
    local tpl="$1" out="$2"; shift 2
    [ -f "$tpl" ] || morir "no encuentro la plantilla $tpl"
    local contenido val v
    contenido="$(cat "$tpl")"
    for v in "$@"; do
        eval "val=\${$v-}"
        contenido="${contenido//\{\{$v\}\}/$val}"
    done
    printf '%s\n' "$contenido" > "$out"
}

# Saca las variables del deploy del ENTORNO, pero las deja como variables del
# shell (`export -n`). Sin esto, artisan las hereda y phpdotenv —que arranca en
# modo immutable— les da prioridad por sobre el .env del sitio: APP_NAME,
# APP_DEBUG, LOG_LEVEL, DB_HOST, DB_PORT y sobre todo DB_PASSWORD se llaman
# igual en los dos lados. Llamar apenas se sourcea config.sh.
desexportar_config() {
    local v
    for v in ${VARS_DEPLOY:-}; do
        export -n "$v" 2>/dev/null || true
    done
}

exigir_comandos() {
    local c faltan=""
    for c in "$@"; do
        command -v "$c" >/dev/null 2>&1 || faltan="$faltan $c"
    done
    [ -z "$faltan" ] || morir "faltan comandos en el servidor:$faltan"
}

# --- .env ---------------------------------------------------------------------
# env_leer CLAVE — imprime el valor (sin comillas) o vacío.
env_leer() {
    [ -f "$ENV_FILE" ] || return 0
    sed -n "s/^$1=//p" "$ENV_FILE" | head -1 | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

# env_escribir CLAVE VALOR — agrega o reemplaza la línea.
env_escribir() {
    local clave="$1" valor="$2"
    if grep -q "^$clave=" "$ENV_FILE" 2>/dev/null; then
        local tmp; tmp="$(mktemp)"
        awk -v k="$clave" -v v="$valor" \
            'BEGIN{FS="="} $1==k && !done {print k "=" v; done=1; next} {print}' \
            "$ENV_FILE" > "$tmp"
        cat "$tmp" > "$ENV_FILE"
        rm -f "$tmp"
    else
        printf '%s=%s\n' "$clave" "$valor" >> "$ENV_FILE"
    fi
}

# --- artisan / composer -------------------------------------------------------
# Todo corre como root y al final se hace chown -R al usuario web, así no
# quedan archivos de caché/log que www-data no pueda escribir.
artisan() { ( cd "$APP_DIR" && "$PHP_BIN" artisan "$@" ); }

componer() {
    ( cd "$APP_DIR" && COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1 \
        composer install --no-dev --prefer-dist --optimize-autoloader --no-progress )
}

arreglar_permisos() {
    chown -R "$WEB_USER:$WEB_GROUP" "$SITE_DIR"
    chmod -R ug+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
    find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 775 {} +
}

# --- MySQL --------------------------------------------------------------------
# En este VPS root entra por socket sin contraseña.
sql() { mysql --protocol=socket -uroot "$@"; }

tabla_existe() {
    local n
    n="$(sql -N -B -e "SELECT COUNT(*) FROM information_schema.tables \
        WHERE table_schema='$DB_NAME' AND table_name='$1';" 2>/dev/null || echo 0)"
    [ "${n:-0}" -gt 0 ]
}

filas_en() {
    sql -N -B -e "SELECT COUNT(*) FROM \`$DB_NAME\`.\`$1\`;" 2>/dev/null || echo 0
}

# --- Salud --------------------------------------------------------------------
chequear_sitio() {
    local esquema="http" codigo
    if [ -d "/etc/letsencrypt/live/$DOMINIO" ]; then esquema="https"; fi
    codigo="$(curl -sk -o /dev/null -w '%{http_code}' --max-time 20 \
        -H "Host: $DOMINIO" "$esquema://127.0.0.1/" || echo 000)"
    case "$codigo" in
        200|301|302) ok "el sitio responde HTTP $codigo" ;;
        000) aviso "no obtuve respuesta del sitio (curl falló)" ;;
        *)   aviso "el sitio respondió HTTP $codigo — revisá /var/log/nginx/$DOMINIO.error.log y $APP_DIR/storage/logs/" ;;
    esac
}
