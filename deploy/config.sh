# ==============================================================================
# deploy/config.sh — TODAS las variables del deploy.
#
# No se ejecuta solo: lo cargan setup.sh y deploy.sh.
# Para secretos o valores propios de tu máquina, crear `config.local.sh`
# (no se versiona) con las mismas variables; pisa lo de acá.
#
# Cualquier variable también se puede pisar desde el entorno:
#   DOMINIO=staging.codeland.com.ar ./deploy.sh
# ==============================================================================

# --- Acceso al servidor -------------------------------------------------------
# Alias de ~/.ssh/config. Con esto alcanza: `ssh mivps` / `scp mivps:...`
SSH_HOST="${SSH_HOST:-mivps2}"
SSH_OPTS="${SSH_OPTS:--o ConnectTimeout=25 -o ServerAliveInterval=15}"

# --- Sitio --------------------------------------------------------------------
DOMINIO="${DOMINIO:-comanda.codeland.com.ar}"
# Dominios extra para el server_name, separados por espacio. Ej: "www.paqueteria.codeland.com.ar"
DOMINIOS_ALIAS="${DOMINIOS_ALIAS:-}"

# Carpeta del sitio en el VPS (se crea si no existe). Acá vive el clon del repo.
SITE_DIR="${SITE_DIR:-/var/www/comanda}"
# Subcarpeta del repo donde está el root de Laravel (en este repo, `app/`).
APP_SUBDIR="${APP_SUBDIR:-.}"

APP_NAME="${APP_NAME:-Tu Comanda}"
APP_LOCALE="${APP_LOCALE:-es}"
APP_TIMEZONE="${APP_TIMEZONE:-America/Argentina/Cordoba}"
APP_DEBUG="${APP_DEBUG:-false}"
LOG_LEVEL="${LOG_LEVEL:-warning}"

# --- Repositorio --------------------------------------------------------------
# El VPS clona por SSH; su clave (root@vps) ya está autorizada en GitHub.
REPO_URL="${REPO_URL:-git@github.com:GatoGauchoAyudante1/comanda.git}"
REPO_BRANCH="${REPO_BRANCH:-main}"

# --- PHP / nginx (valores reales de este VPS) ---------------------------------
PHP_BIN="${PHP_BIN:-php8.4}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.4-fpm}"
# Este VPS expone FPM por TCP, no por socket unix.
PHP_FPM_PASS="${PHP_FPM_PASS:-127.0.0.1:19000}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"
NGINX_SITES="${NGINX_SITES:-/etc/nginx/sites-available}"
NGINX_ENABLED="${NGINX_ENABLED:-/etc/nginx/sites-enabled}"
# nginx incluye sites-enabled/*.conf — el archivo TIENE que terminar en .conf
NGINX_CONF_NAME="${NGINX_CONF_NAME:-${DOMINIO}.conf}"
UPLOAD_MAX="${UPLOAD_MAX:-32M}"

# --- Base de datos ------------------------------------------------------------
DB_NAME="${DB_NAME:-comanda}"
DB_USER="${DB_USER:-root}"
# Vacío => se genera una contraseña aleatoria en el primer setup y queda en el
# .env del servidor. En los siguientes setup se reutiliza la del .env.
DB_PASSWORD="${DB_PASSWORD:-}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

# --- Carga inicial de datos (sólo en setup.sh) --------------------------------
# .sql con el que arranca producción. Ruta relativa a la raíz del repo; vacío =
# no importar nada. Viaja por scp porque tools/ está gitignoreado (datos reales).
SQL_INICIAL="${SQL_INICIAL:-tools/out/laromana.sql}"

# Cómo se vuelca ese .sql:
#   dump-completo  → mysqldump con CREATE TABLE. Se importa ANTES de migrate, y
#                    después `migrate --force` aplica sólo lo que el dump no
#                    traiga. Los seeders no corren: los datos ya vienen adentro.
#   post-migracion → sólo INSERTs, contra un esquema ya migrado y sembrado.
#
# OJO: tools/out/migracion_legacy.sql es `post-migracion` pero está generado
# contra el esquema PREVIO a la normalización (2026_07_22_000001..000008), que
# es un ETL pensado para correr sobre los datos legacy ya importados. En una
# instalación nueva ese orden se invierte y la importación falla. Por eso el
# default es el dump de la base local, que ya viene normalizada.
SQL_MODO="${SQL_MODO:-dump-completo}"

# Corre `php artisan db:seed` (sucursales, roles/permisos, usuarios). Sólo si la
# base quedó vacía: si el dump trajo datos, no se toca nada.
SEMBRAR="${SEMBRAR:-1}"

# --- Assets (Vite) ------------------------------------------------------------
# Se compilan ACÁ y se suben ya construidos: son 130 KB comprimidos, contra un
# `npm ci` de cientos de MB en un VPS con ~1 GB de RAM libre. Además, si el
# build falla, falla acá y el servidor ni se entera.
# (En el VPS hay Node 22 vía nvm, pero fuera del PATH de un ssh no interactivo.)
#   local = compila tu working tree. Como el auto-commit deja el árbol igual a
#           main antes de compilar, es equivalente a `main` y mucho más rápido.
#   main  = clona origin/main en un temporal y hace npm ci + build ahí. Más
#           lento y a prueba de un node_modules local desactualizado.
ASSETS_ORIGEN="${ASSETS_ORIGEN:-local}"

# --- SSL ----------------------------------------------------------------------
SSL="${SSL:-1}"
SSL_EMAIL="${SSL_EMAIL:-it.codeland@gmail.com}"

# --- Extras del servidor ------------------------------------------------------
# Cron con `schedule:run` cada minuto.
SCHEDULER="${SCHEDULER:-1}"
# Servicio systemd con `queue:work` (QUEUE_CONNECTION=database).
QUEUE_WORKER="${QUEUE_WORKER:-1}"
SERVICIO_QUEUE="${SERVICIO_QUEUE:-comanda-queue}"

# --- Comportamiento del deploy ------------------------------------------------
# `php artisan down` mientras corren migraciones y composer.
MANTENIMIENTO="${MANTENIMIENTO:-1}"

# Commitea y pushea solo lo que haya sin commitear antes de desplegar, con un
# mensaje automático. Nunca pregunta nada. Lo que no querés que suba, al
# .gitignore (la raíz ya ignora credenciales/, tools/, mockup_data/, todo/).
AUTO_COMMIT="${AUTO_COMMIT:-1}"

# Los scripts remotos NO viajan por scp: llegan al VPS con el propio repo
# (deploy/ está versionado). Lo único que se sube son estos dos archivos.
ASSETS_REMOTO="${ASSETS_REMOTO:-/tmp/comanda-build.tar.gz}"
SQL_REMOTO="${SQL_REMOTO:-/tmp/comanda-datos.sql.gz}"

# --- Variables que viajan al VPS ----------------------------------------------
# Van como un bloque de `export` al principio del comando ssh; del otro lado,
# este mismo archivo las respeta porque todo acá es ${VAR:-default}.
#
# IMPORTANTE: apenas los scripts remotos terminan de leer esta config, las sacan
# del ENTORNO con `export -n` (ver desexportar_config en remote/_comun.sh).
# Varias se llaman igual que las de Laravel — APP_NAME, APP_DEBUG, APP_LOCALE,
# APP_TIMEZONE, LOG_LEVEL, DB_HOST, DB_PORT, DB_PASSWORD — y phpdotenv arranca
# en modo *immutable*: si la variable ya existe en el entorno, el .env NO la
# pisa. Con DB_PASSWORD vacía (se genera en el servidor) artisan se conectaría
# sin contraseña, y peor: `config:cache` hornearía esos valores en la caché.
VARS_DEPLOY="
DOMINIO DOMINIOS_ALIAS SITE_DIR APP_SUBDIR APP_NAME APP_LOCALE APP_TIMEZONE
APP_DEBUG LOG_LEVEL REPO_URL REPO_BRANCH PHP_BIN PHP_FPM_SERVICE PHP_FPM_PASS
WEB_USER WEB_GROUP NGINX_SITES NGINX_ENABLED NGINX_CONF_NAME UPLOAD_MAX
DB_NAME DB_USER DB_PASSWORD DB_HOST DB_PORT SEMBRAR SQL_MODO SSL SSL_EMAIL
SCHEDULER QUEUE_WORKER SERVICIO_QUEUE MANTENIMIENTO ASSETS_REMOTO SQL_REMOTO
"
