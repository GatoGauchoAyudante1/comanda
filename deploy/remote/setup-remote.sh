#!/usr/bin/env bash
# ==============================================================================
# setup-remote.sh — se ejecuta DENTRO del VPS, desde el propio checkout
# (/var/www/paqueteria/deploy/remote/). Lo dispara setup.sh, que antes clona o
# actualiza el repo; por eso la versión que corre es siempre la de la rama.
#
# Deja el sitio andando desde cero y es IDEMPOTENTE: se puede volver a correr
# sin romper nada (no pisa el .env, no re-importa datos, no re-emite el
# certificado, no vuelve a crear el usuario de MySQL).
# ==============================================================================
set -euo pipefail

AQUI="$(cd "$(dirname "$0")" && pwd)"
DEPLOY_DIR="$(cd "$AQUI/.." && pwd)"
# La config viene versionada; setup.sh ya exportó al entorno todo lo que quiera
# pisar, y config.sh lo respeta porque usa ${VAR:-default}.
# shellcheck source=/dev/null
. "$DEPLOY_DIR/config.sh"
# shellcheck source=/dev/null
. "$AQUI/_comun.sh"
# Ya las leímos: fuera del entorno, para que artisan no las confunda con el .env.
desexportar_config

APP_DIR="$SITE_DIR/$APP_SUBDIR"
ROOT_PUBLICO="$APP_DIR/public"
ENV_FILE="$APP_DIR/.env"
PLANTILLAS="$DEPLOY_DIR/templates"

OMITIR_ASSETS="${OMITIR_ASSETS:-0}"
OMITIR_SSL="${OMITIR_SSL:-0}"
OMITIR_DATOS="${OMITIR_DATOS:-0}"
FORZAR_DATOS="${FORZAR_DATOS:-0}"
FORZAR_NGINX="${FORZAR_NGINX:-0}"

# ------------------------------------------------------------------ 1. checks
paso "Verificando el servidor"
exigir_comandos git nginx mysql composer curl tar "$PHP_BIN"
info "$("$PHP_BIN" -v | head -1)"
info "$(nginx -v 2>&1)"
info "$(mysql --version)"
systemctl is-active --quiet "$PHP_FPM_SERVICE" \
    || morir "$PHP_FPM_SERVICE no está corriendo"
ok "PHP-FPM ($PHP_FPM_SERVICE) activo, FPM en $PHP_FPM_PASS"

# --------------------------------------------------------------- 2. el código
# El clone/fetch ya lo hizo el arranque que mandó setup.sh: sin eso, este
# script ni existiría en el servidor.
paso "Código"
[ -f "$APP_DIR/artisan" ] || morir "no encuentro $APP_DIR/artisan (¿APP_SUBDIR está bien?)"
ok "$REPO_BRANCH @ $(git -C "$SITE_DIR" rev-parse --short HEAD) — $(git -C "$SITE_DIR" log -1 --pretty=%s)"

# ------------------------------------------------------------ 3. base de datos
paso "Base de datos MySQL"
sql -e "SELECT 1;" >/dev/null 2>&1 || morir "no puedo entrar a MySQL como root por socket"

# Contraseña: la de config, o la que ya está en el .env, o una nueva.
DB_PASS_ANTERIOR="$(env_leer DB_PASSWORD)"
if [ -n "${DB_PASSWORD:-}" ]; then
    PASS="$DB_PASSWORD"
    ORIGEN_PASS="config.sh"
elif [ -n "$DB_PASS_ANTERIOR" ]; then
    PASS="$DB_PASS_ANTERIOR"
    ORIGEN_PASS=".env existente"
else
    PASS="$(head -c 48 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 28)"
    ORIGEN_PASS="generada ahora"
fi
case "$PASS" in
    *"'"*|*'\'*) morir "la contraseña de la base no puede tener comillas simples ni barras invertidas" ;;
esac
info "contraseña de la base: $ORIGEN_PASS"

sql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$PASS';
CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$PASS';
ALTER USER '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
ok "base \`$DB_NAME\` y usuario '$DB_USER' listos"

if tabla_existe migrations && [ "$(filas_en migrations)" -gt 0 ]; then
    info "la base ya tiene $(filas_en migrations) migraciones corridas"
fi

# ¿Ya hay datos de verdad cargados? (no alcanza con que existan las tablas).
# Una instalación nueva puede no tener pedidos todavía, por eso también se
# consideran los usuarios y productos creados por los seeders de Comanda.
hay_datos() {
    { tabla_existe users && [ "$(filas_en users)" -gt 0 ]; } \
        || { tabla_existe products && [ "$(filas_en products)" -gt 0 ]; } \
        || { tabla_existe orders && [ "$(filas_en orders)" -gt 0 ]; }
}

# Vuelca $SQL_REMOTO en la base. Deja IMPORTADO=1 si efectivamente importó.
IMPORTADO=0
importar_datos() {
    if [ "$OMITIR_DATOS" = "1" ]; then
        aviso "salteado por --sin-datos"
    elif [ ! -f "$SQL_REMOTO" ]; then
        info "no se envió ningún .sql: nada que importar"
    elif hay_datos && [ "$FORZAR_DATOS" != "1" ]; then
        info "la base ya contiene datos de Comanda: no importo (usá --forzar-datos)"
    else
        info "importando el volcado inicial…"
        gunzip -c "$SQL_REMOTO" \
            | mysql --protocol=socket -uroot --default-character-set=utf8mb4 "$DB_NAME" \
            || morir "falló la importación del .sql"
        IMPORTADO=1
        ok "datos importados — usuarios: $(filas_en users), productos: $(filas_en products), pedidos: $(filas_en orders)"
        rm -f "$SQL_REMOTO"
    fi
}

# ---------------------------------------------------------------- 4. .env
paso "Archivo .env"
if [ -f "$ENV_FILE" ]; then
    ok ".env ya existe, no lo toco (sólo sincronizo credenciales de la base)"
    env_escribir DB_DATABASE "$DB_NAME"
    env_escribir DB_USERNAME "$DB_USER"
    env_escribir DB_PASSWORD "\"$PASS\""
else
    if [ "${SSL:-0}" = "1" ] && [ "$OMITIR_SSL" != "1" ]; then
        APP_URL="https://$DOMINIO"; SESSION_SECURE_COOKIE=true
    else
        APP_URL="http://$DOMINIO"; SESSION_SECURE_COOKIE=false
    fi
    APP_KEY=""
    DB_PASSWORD="$PASS"
    render "$PLANTILLAS/env.tpl" "$ENV_FILE" \
        APP_NAME APP_KEY APP_DEBUG APP_URL APP_LOCALE APP_TIMEZONE LOG_LEVEL \
        DB_HOST DB_PORT DB_NAME DB_USER DB_PASSWORD DOMINIO SESSION_SECURE_COOKIE
    chmod 640 "$ENV_FILE"
    ok ".env creado ($APP_URL)"
fi

# ------------------------------------------------------------- 5. dependencias
paso "Dependencias PHP (composer install --no-dev)"
componer
ok "vendor/ instalado"

# ------------------------------------------------------------------ 6. APP_KEY
paso "Clave de la aplicación"
artisan config:clear >/dev/null 2>&1 || true
if [ -z "$(env_leer APP_KEY)" ]; then
    artisan key:generate --force --no-interaction
    ok "APP_KEY generada"
else
    ok "APP_KEY ya estaba definida"
fi

# ------------------------------------------------------------------ 7. assets
paso "Assets compilados (Vite)"
if [ "$OMITIR_ASSETS" = "1" ]; then
    aviso "salteado por --sin-assets"
elif [ -f "$ASSETS_REMOTO" ]; then
    rm -rf "$ROOT_PUBLICO/build"
    tar -xzf "$ASSETS_REMOTO" -C "$ROOT_PUBLICO"
    [ -f "$ROOT_PUBLICO/build/manifest.json" ] || morir "el paquete de assets no trae manifest.json"
    rm -f "$ASSETS_REMOTO"
    ok "public/build actualizado"
else
    morir "no llegó $ASSETS_REMOTO al servidor"
fi

# ------------------------------------------------------------- 8. storage:link
paso "Enlace de storage"
if [ -L "$ROOT_PUBLICO/storage" ]; then
    ok "public/storage ya enlazado"
else
    artisan storage:link
fi

# ------------------------------------------------- 9. volcado inicial (.sql)
# El dump completo va ANTES de migrate: trae su propio esquema y su propia
# tabla `migrations`, así que después `migrate --force` sólo aplica lo que el
# dump no tenga. Al revés no funciona (ver SQL_MODO en config.sh).
if [ "$SQL_MODO" = "dump-completo" ]; then
    paso "Carga inicial de datos (dump completo)"
    importar_datos
fi

# ------------------------------------------------------------- 10. migraciones
paso "Migraciones"
artisan migrate --force --no-interaction
ok "migraciones al día"

# ---------------------------------------------- 11. volcado inicial (post-migr.)
if [ "$SQL_MODO" != "dump-completo" ]; then
    paso "Carga inicial de datos"
    importar_datos
fi

# --------------------------------------------------------------- 12. seeders
paso "Datos base (sucursales, roles, usuarios)"
USUARIOS=0
if tabla_existe users; then USUARIOS="$(filas_en users)"; fi
if [ "${SEMBRAR:-0}" != "1" ]; then
    aviso "SEMBRAR=0, no corro los seeders"
elif [ "$IMPORTADO" = "1" ]; then
    info "los usuarios y las sucursales vinieron en el volcado: no siembro"
elif [ "$USUARIOS" -eq 0 ] || [ "$FORZAR_DATOS" = "1" ]; then
    artisan db:seed --force --no-interaction
    ok "seeders ejecutados"
else
    info "ya hay $USUARIOS usuarios: no re-siembro (usá --forzar-datos si querés)"
fi

# ------------------------------------------------------------------ 13. cachés
paso "Cachés de producción"
artisan optimize:clear >/dev/null
artisan config:cache >/dev/null
artisan route:cache  >/dev/null
artisan view:cache   >/dev/null
artisan event:cache  >/dev/null 2>&1 || true
ok "config, rutas y vistas cacheadas"

# --------------------------------------------------------------- 14. permisos
paso "Permisos"
arreglar_permisos
ok "$SITE_DIR es de $WEB_USER:$WEB_GROUP"

# ------------------------------------------------------------------ 15. nginx
paso "Virtual host de nginx"
CONF="$NGINX_SITES/$NGINX_CONF_NAME"
SERVER_NAMES="$DOMINIO${DOMINIOS_ALIAS:+ $DOMINIOS_ALIAS}"

if [ -f "$CONF" ] && grep -q "managed by Certbot" "$CONF" && [ "$FORZAR_NGINX" != "1" ]; then
    aviso "$CONF ya fue tocado por certbot: no lo piso (usá --forzar-nginx para regenerarlo)"
else
    if [ -f "$CONF" ]; then cp -a "$CONF" "$CONF.bak.$(date +%Y%m%d%H%M%S)"; fi
    render "$PLANTILLAS/nginx.conf.tpl" "$CONF" \
        DOMINIO SERVER_NAMES ROOT_PUBLICO PHP_FPM_PASS UPLOAD_MAX
    ok "escrito $CONF"
fi

ln -sfn "$CONF" "$NGINX_ENABLED/$NGINX_CONF_NAME"
nginx -t || morir "la configuración de nginx no valida"
systemctl reload nginx
ok "nginx recargado — sitio habilitado"

# -------------------------------------------------------------------- 16. SSL
paso "Certificado SSL"
if [ "${SSL:-0}" != "1" ] || [ "$OMITIR_SSL" = "1" ]; then
    aviso "SSL desactivado — el sitio queda en HTTP"
elif [ -d "/etc/letsencrypt/live/$DOMINIO" ]; then
    ok "ya existe un certificado para $DOMINIO (certbot lo renueva solo)"
elif ! command -v certbot >/dev/null 2>&1; then
    aviso "certbot no está instalado: salteo el SSL"
else
    DOMS="-d $DOMINIO"
    for d in $DOMINIOS_ALIAS; do DOMS="$DOMS -d $d"; done
    info "pidiendo certificado a Let's Encrypt (el DNS de $DOMINIO tiene que apuntar acá)"
    # Autenticador `webroot`, no `--nginx`: el plugin de nginx inyecta un
    # `location =` con el token en el server block y acá terminaba devolviendo
    # 404, mientras que un archivo real bajo .well-known/acme-challenge/ se
    # sirve perfecto. El instalador sigue siendo nginx, así que igual escribe
    # el bloque 443 y el redirect.
    mkdir -p "$ROOT_PUBLICO/.well-known/acme-challenge"
    chown -R "$WEB_USER:$WEB_GROUP" "$ROOT_PUBLICO/.well-known"
    # shellcheck disable=SC2086
    if certbot run --authenticator webroot --webroot-path "$ROOT_PUBLICO" \
        --installer nginx $DOMS --non-interactive --agree-tos --redirect \
        -m "$SSL_EMAIL" --keep-until-expiring; then
        ok "HTTPS activo en https://$DOMINIO"
        env_escribir APP_URL "https://$DOMINIO"
        env_escribir SESSION_SECURE_COOKIE "true"
        artisan config:cache >/dev/null
        arreglar_permisos
    else
        aviso "certbot falló (¿el DNS todavía no propagó?). El sitio queda en HTTP."
        aviso "cuando el DNS esté listo: certbot --nginx -d $DOMINIO --redirect -m $SSL_EMAIL"
    fi
fi

# ---------------------------------------------------- 17. scheduler y colas
paso "Tareas programadas y colas"
CRON_FILE="/etc/cron.d/${SERVICIO_QUEUE}-scheduler"
if [ "${SCHEDULER:-0}" = "1" ]; then
    cat > "$CRON_FILE" <<CRON
# Scheduler de $APP_NAME — generado por deploy/setup.sh
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
* * * * * $WEB_USER cd $APP_DIR && /usr/bin/$PHP_BIN artisan schedule:run >> /dev/null 2>&1
CRON
    chmod 644 "$CRON_FILE"
    ok "cron del scheduler en $CRON_FILE"
else
    rm -f "$CRON_FILE"
    info "scheduler desactivado"
fi

UNIT="/etc/systemd/system/$SERVICIO_QUEUE.service"
if [ "${QUEUE_WORKER:-0}" = "1" ]; then
    render "$PLANTILLAS/queue.service.tpl" "$UNIT" \
        APP_NAME DOMINIO WEB_USER WEB_GROUP APP_DIR PHP_BIN SERVICIO_QUEUE
    touch "/var/log/$SERVICIO_QUEUE.log"
    chown "$WEB_USER:$WEB_GROUP" "/var/log/$SERVICIO_QUEUE.log"
    systemctl daemon-reload
    systemctl enable --now "$SERVICIO_QUEUE" >/dev/null 2>&1 || true
    systemctl restart "$SERVICIO_QUEUE"
    if systemctl is-active --quiet "$SERVICIO_QUEUE"; then
        ok "worker de colas activo ($SERVICIO_QUEUE)"
    else
        aviso "el worker $SERVICIO_QUEUE no arrancó — mirá: journalctl -u $SERVICIO_QUEUE -n 30"
    fi
else
    systemctl disable --now "$SERVICIO_QUEUE" >/dev/null 2>&1 || true
    info "worker de colas desactivado"
fi

# ------------------------------------------------------------------ 18. salud
paso "Chequeo final"
systemctl reload "$PHP_FPM_SERVICE" || true
chequear_sitio

echo
echo "  ${R_GREEN}${R_BOLD}Listo.${R_RESET}"
echo "  Sitio    : $(env_leer APP_URL)"
echo "  Carpeta  : $SITE_DIR  (Laravel en $APP_DIR)"
echo "  Commit   : $(git -C "$SITE_DIR" rev-parse --short HEAD) — $(git -C "$SITE_DIR" log -1 --pretty=%s)"
echo "  Base     : $DB_NAME / usuario $DB_USER"
if [ "$ORIGEN_PASS" = "generada ahora" ]; then
    echo "  Password : $PASS   ${R_YELLOW}(guardala: queda sólo en $ENV_FILE)${R_RESET}"
fi
echo "  Nginx    : $CONF"
