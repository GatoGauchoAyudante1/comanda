# ==============================================================================
# deploy/lib.sh — funciones comunes de los scripts locales.
# Se carga con `source`, no se ejecuta solo.
# ==============================================================================

if [ -t 1 ] && [ -z "${NO_COLOR:-}" ]; then
    C_RESET=$'\033[0m'; C_DIM=$'\033[2m'; C_BOLD=$'\033[1m'
    C_RED=$'\033[31m'; C_GREEN=$'\033[32m'; C_YELLOW=$'\033[33m'; C_BLUE=$'\033[34m'
else
    C_RESET=; C_DIM=; C_BOLD=; C_RED=; C_GREEN=; C_YELLOW=; C_BLUE=
fi

paso()  { printf '\n%s==>%s %s%s%s\n' "$C_BLUE" "$C_RESET" "$C_BOLD" "$*" "$C_RESET"; }
info()  { printf '    %s\n' "$*"; }
ok()    { printf '    %s✓%s %s\n' "$C_GREEN" "$C_RESET" "$*"; }
aviso() { printf '    %s!%s %s\n' "$C_YELLOW" "$C_RESET" "$*"; }
error() { printf '\n%s✗ %s%s\n' "$C_RED" "$*" "$C_RESET" >&2; }
morir() { error "$*"; exit 1; }

# Pregunta sí/no. Con AUTO=1 (o sin TTY) responde que sí y sigue.
confirmar() {
    local pregunta="$1"
    if [ "${AUTO:-0}" = "1" ] || [ ! -t 0 ]; then
        info "$pregunta -> sí (modo automático)"
        return 0
    fi
    local r
    read -r -p "    $pregunta [s/N] " r
    case "$r" in [sSyY]*) return 0 ;; *) return 1 ;; esac
}

# --- Config -------------------------------------------------------------------
# Carga config.sh y, si existe, config.local.sh.
cargar_config() {
    local dir="$1"
    # shellcheck source=/dev/null
    . "$dir/config.sh"
    if [ -f "$dir/config.local.sh" ]; then
        # shellcheck source=/dev/null
        . "$dir/config.local.sh"
        info "config.local.sh cargado"
    fi
    # NGINX_CONF_NAME depende de DOMINIO: si lo pisaron, recalcular.
    case "$NGINX_CONF_NAME" in
        *.conf) : ;;
        *) NGINX_CONF_NAME="${NGINX_CONF_NAME}.conf" ;;
    esac
}

# Imprime el bloque de `export` que encabeza el script que corre en el VPS.
# Así no hace falta subir ningún archivo de configuración. La lista es
# VARS_DEPLOY, definida en config.sh (la comparten las dos puntas).
exportaciones_remotas() {
    local v val
    for v in $VARS_DEPLOY; do
        eval "val=\${$v-}"
        printf 'export %s=%s\n' "$v" "$(printf '%q' "$val")"
    done
}

# Manda un script al VPS por stdin. El script remoto que se invoca adentro lee
# de su propio archivo, así que no compite por stdin.
# shellcheck disable=SC2086
ejecutar_remoto() { ssh $SSH_OPTS "$SSH_HOST" 'bash -s' < "$1"; }

# --- Plantillas ---------------------------------------------------------------
# render <plantilla> <salida> VAR1 VAR2 ...   — reemplaza {{VAR}} por su valor.
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

# --- SSH / SCP ----------------------------------------------------------------
# shellcheck disable=SC2086
remoto()      { ssh $SSH_OPTS "$SSH_HOST" "$@"; }
# shellcheck disable=SC2086
remoto_bash() { ssh $SSH_OPTS "$SSH_HOST" 'bash -s' ; }   # el script entra por stdin
# shellcheck disable=SC2086
subir()       { scp $SSH_OPTS -q -r "$@"; }

verificar_ssh() {
    paso "Probando acceso a $SSH_HOST"
    remoto 'echo ok' >/dev/null 2>&1 || morir "no puedo conectarme por ssh a '$SSH_HOST'. Revisá ~/.ssh/config."
    ok "conexión ssh a $SSH_HOST"
}

verificar_comandos_locales() {
    local c
    for c in "$@"; do
        command -v "$c" >/dev/null 2>&1 || morir "falta el comando '$c' en esta máquina."
    done
}

# --- Assets -------------------------------------------------------------------
# Compila los assets y deja el tar.gz en $1. Usa $ASSETS_ORIGEN.
compilar_assets() {
    local salida="$1"
    local tmp src
    verificar_comandos_locales npm tar

    if [ "$ASSETS_ORIGEN" = "local" ]; then
        src="$RAIZ_REPO/$APP_SUBDIR"
        if [ ! -d "$src/node_modules" ]; then
            info "no hay node_modules: npm ci (esta vez tarda)"
            ( cd "$src" && npm ci --no-audit --no-fund ) || morir "falló 'npm ci'"
        fi
        ( cd "$src" && npm run build ) || morir "falló 'npm run build'"
    else
        tmp="$(mktemp -d)"
        trap 'rm -rf "$tmp"' RETURN
        info "clonando $REPO_BRANCH para compilar assets idénticos a lo que se despliega"
        git clone --quiet --depth 1 --branch "$REPO_BRANCH" "$REPO_URL" "$tmp/src" \
            || morir "no pude clonar $REPO_URL rama $REPO_BRANCH"
        src="$tmp/src/$APP_SUBDIR"
        info "npm ci (puede tardar un minuto)"
        ( cd "$src" && npm ci --no-audit --no-fund ) || morir "falló 'npm ci'"
        ( cd "$src" && npm run build ) || morir "falló 'npm run build'"
    fi

    [ -d "$src/public/build" ] || morir "no se generó public/build"
    tar -czf "$salida" -C "$src/public" build || morir "no pude empaquetar public/build"
    ok "assets compilados ($(du -h "$salida" | cut -f1))"
}

# Commitea lo que haya suelto (con mensaje automático) y lo pushea.
# Lo que no tiene que subir se filtra por .gitignore, no acá.
auto_commit() {
    [ -d "$RAIZ_REPO/.git" ] || { aviso "esto no es un repo git"; return 0; }
    local sucio n mensaje

    if [ "${AUTO_COMMIT:-1}" != "1" ]; then
        sucio="$(git -C "$RAIZ_REPO" status --porcelain)"
        if [ -n "$sucio" ]; then
            aviso "hay cambios sin commitear y AUTO_COMMIT=0: se despliega lo que ya está en $REPO_BRANCH"
        fi
        return 0
    fi

    sucio="$(git -C "$RAIZ_REPO" status --porcelain)"
    if [ -n "$sucio" ]; then
        n="$(printf '%s\n' "$sucio" | wc -l | tr -d ' ')"
        printf '%s\n' "$sucio" | head -8 | sed 's/^/        /'
        if [ "$n" -gt 8 ]; then info "… y $((n - 8)) más"; fi
        mensaje="deploy: $(date '+%Y-%m-%d %H:%M') ($n archivos)"
        git -C "$RAIZ_REPO" add -A
        git -C "$RAIZ_REPO" commit -q -m "$mensaje" || morir "el commit automático falló"
        ok "commit automático: $mensaje"
    else
        info "nada sin commitear"
    fi

    # Pushear siempre: puede haber commits locales de antes.
    if git -C "$RAIZ_REPO" push -q origin "HEAD:$REPO_BRANCH" 2>/dev/null; then
        ok "$REPO_BRANCH en GitHub: $(git -C "$RAIZ_REPO" rev-parse --short HEAD)"
    else
        error "no pude pushear a origin/$REPO_BRANCH"
        info "suele ser que la rama remota tiene commits que vos no tenés. Probá:"
        info "    git -C '$RAIZ_REPO' pull --rebase origin $REPO_BRANCH"
        exit 1
    fi
}

# Confirma que lo que hay en GitHub es lo que tenemos acá.
verificar_sincronizado() {
    local local_head remoto_head
    local_head="$(git -C "$RAIZ_REPO" rev-parse HEAD 2>/dev/null || true)"
    remoto_head="$(git -C "$RAIZ_REPO" ls-remote origin "refs/heads/$REPO_BRANCH" 2>/dev/null | cut -f1 || true)"
    if [ -z "$remoto_head" ]; then
        morir "no pude leer origin/$REPO_BRANCH. ¿Tenés acceso a $REPO_URL?"
    elif [ "$local_head" != "$remoto_head" ]; then
        aviso "tu HEAD ($(echo "$local_head" | cut -c1-7)) != origin/$REPO_BRANCH ($(echo "$remoto_head" | cut -c1-7))"
        aviso "se despliega lo de GitHub, no tu copia."
    fi
}
