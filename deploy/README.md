# Deploy de Transporte Elisa

Scripts para levantar y actualizar `paqueteria.codeland.com.ar` en el VPS **sin
tocar nada a mano**. Todo se dispara desde esta máquina; el único requisito es
que funcione `ssh mivps`.

```bash
cd deploy

./setup.sh      # primera puesta en marcha (o re-instalación: es idempotente)
./deploy.sh     # actualizar el sitio con lo último de la rama main
```

> Los `.sh` se corren desde **Git Bash** (o WSL), no desde PowerShell.
> Si el permiso de ejecución se perdió: `bash setup.sh`.

---

## Qué hace `setup.sh`

Todo lo que normalmente se hace a mano la primera vez:

| # | Paso |
|---|---|
| 0 | Commitea y pushea lo que tengas suelto, con mensaje automático |
| 1 | Chequea que en el VPS estén nginx, PHP 8.4, MySQL, composer y git |
| 2 | Clona `main` desde GitHub en `/var/www/paqueteria` (o la actualiza si ya está) |
| 3 | Crea la base `transporte_elisa` y su usuario, con **contraseña generada sola** |
| 4 | Escribe el `.env` de producción y genera el `APP_KEY` |
| 5 | `composer install --no-dev --optimize-autoloader` |
| 6 | Sube los assets de Vite ya compilados y arma `public/storage` |
| 7 | Vuelca el dump inicial, después `migrate --force` (y `db:seed` sólo si la base quedó vacía) |
| 8 | `config/route/view:cache` y permisos para `www-data` |
| 9 | Escribe el virtual host de nginx, lo habilita y recarga |
| 10 | Pide el certificado a Let's Encrypt con certbot (`--redirect`) |
| 11 | Deja el cron del scheduler y el worker de colas (systemd) andando |
| 12 | Chequea que el sitio responda |

Es **idempotente**: volver a correrlo no pisa el `.env`, no re-importa los datos,
no re-emite el certificado ni rompe el vhost que certbot haya modificado.

```bash
./setup.sh --sin-ssl        # el DNS todavía no propagó
./setup.sh --sin-datos      # sin volcar el .sql inicial
./setup.sh --forzar-datos   # re-siembra y re-importa aunque ya haya guías cargadas
./setup.sh --forzar-nginx   # regenera el vhost aunque certbot lo haya tocado
./setup.sh --sin-commit     # no commitea ni pushea nada
./setup.sh --si             # sin preguntas (para automatizar)
```

## Qué hace `deploy.sh`

Actualización de todos los días. No toca `.env`, ni nginx, ni el certificado, ni
los datos:

commit + push automático → `artisan down` → `git reset --hard origin/main` →
`composer install --no-dev` *(sólo si cambió `composer.lock`)* → assets →
`migrate --force` → cachés → permisos → reload de PHP-FPM y del worker →
`artisan up` → chequeo.

```bash
./deploy.sh --sin-assets        # sólo código, no recompila el front
./deploy.sh --sin-migraciones   # no corre migrate
./deploy.sh --sin-mantenimiento # no baja el sitio mientras despliega
./deploy.sh --sin-commit        # no commitea ni pushea nada
./deploy.sh --con-composer      # composer install aunque el lock no haya cambiado
```

### Commit automático

Nunca te pide un mensaje. Si hay algo sin commitear, hace `git add -A`, commitea
como `deploy: 2026-07-22 04:30 (12 archivos)` y pushea a `main`. Lo que **no**
tiene que subir se filtra por `.gitignore`, no por criterio del script: la raíz
del repo ignora todo salvo `app/` y `deploy/`, así que `credenciales/`, `tools/`,
`mockup_data/` y `todo/` quedan afuera. Si agregás algo que no debe viajar a
GitHub, el lugar donde arreglarlo es el `.gitignore` de la raíz.

Se apaga con `--sin-commit` o con `AUTO_COMMIT=0` en la config.

## Los otros dos

```bash
./artisan.sh migrate:status     # cualquier comando artisan en el VPS
./artisan.sh                    # shell en /var/www/paqueteria/app

./backup-db.sh                  # mysqldump + descarga a deploy/backups/
./backup-db.sh --solo-vps       # lo deja en /var/www/backups/paqueteria
```

---

## Variables

Todo lo configurable vive en [`config.sh`](config.sh): dominio, rutas, repo,
usuario/base de MySQL, versión de PHP, SSL, scheduler, colas.

Tres formas de cambiar un valor, de menor a mayor prioridad:

1. editar `config.sh` (es lo versionado, el default del proyecto);
2. crear `config.local.sh` con las líneas que quieras pisar (**no se versiona**,
   es el lugar para secretos y para pruebas);
3. pasarlo por entorno en la llamada:

```bash
DOMINIO=staging.codeland.com.ar SITE_DIR=/var/www/paqueteria-staging ./setup.sh
```

### Las que más se tocan

| Variable | Default | Para qué |
|---|---|---|
| `SSH_HOST` | `mivps` | Alias de `~/.ssh/config` |
| `DOMINIO` | `paqueteria.codeland.com.ar` | Dominio del sitio |
| `SITE_DIR` | `/var/www/paqueteria` | Carpeta del sitio (se crea si no está) |
| `APP_SUBDIR` | `app` | Dónde está el root de Laravel dentro del repo |
| `REPO_URL` / `REPO_BRANCH` | …`/paqueteria.git` / `main` | Qué se despliega |
| `DB_PASSWORD` | vacío | Vacío = se genera sola en el primer setup |
| `SQL_INICIAL` | `tools/out/transporte_elisa.sql` | Volcado inicial; vacío = ninguno |
| `SQL_MODO` | `dump-completo` | `dump-completo` = se importa antes de migrate; `post-migracion` = después |
| `AUTO_COMMIT` | `1` | Commitea y pushea solo, sin pedir mensaje |
| `ASSETS_ORIGEN` | `local` | `local` = tu working tree; `main` = clon limpio + `npm ci` |
| `SSL` | `1` | `0` para dejarlo en HTTP |
| `QUEUE_WORKER` / `SCHEDULER` | `1` | Worker systemd y cron de `schedule:run` |

---

## Cómo está armado el VPS (verificado)

- Ubuntu 24.04 · nginx 1.28 · MySQL 8.0.45 · certbot 2.9
- PHP 8.4 con FPM escuchando en **TCP `127.0.0.1:19000`** (no socket unix),
  pool corriendo como `www-data`
- nginx incluye `sites-enabled/*.conf` → el vhost **tiene que terminar en `.conf`**
- root entra a MySQL por socket sin contraseña
- la clave SSH del VPS ya está autorizada en GitHub (`ssh -T git@github.com` responde)

### Qué viaja por `scp` (y qué no)

Casi nada. `deploy/` está versionado, así que los scripts remotos y las
plantillas llegan al VPS con el propio `git fetch`; la configuración viaja como
un bloque de `export` al principio del comando ssh. Un deploy es, en esencia:

```bash
scp build.tar.gz mivps:/tmp/…                          # 130 KB
ssh mivps 'git reset --hard origin/main && bash deploy/remote/deploy-remote.sh'
```

Sólo dos archivos se suben, y por buenas razones:

| Archivo | Cuándo | Por qué no viene del repo |
|---|---|---|
| `build.tar.gz` (130 KB) | cada deploy | `public/build` está gitignoreado |
| `datos.sql.gz` (~200 KB) | sólo `setup.sh`, una vez | `tools/` está gitignoreado a propósito: son datos reales de clientes |

### De dónde salen los datos de producción

De `tools/out/transporte_elisa.sql`: un `mysqldump` completo de la base local, ya
normalizada. Producción arranca como copia exacta de lo que tenés funcionando
acá — 3377 guías, 1070 clientes, 1235 destinatarios, 16 localidades.

Se importa **antes** de `migrate`, porque el dump trae su propio esquema y su
propia tabla `migrations`; después, `migrate --force` aplica sólo lo que el dump
no tenga (así un dump viejo se pone al día solo). Los seeders no corren: los
usuarios y las sucursales vienen adentro.

**No usar `tools/out/migracion_legacy.sql`.** Está generado contra el esquema
anterior a la normalización, y las migraciones `2026_07_22_000001`–`000008` son
un ETL escrito para correr *encima* de esos datos ya importados. En una
instalación nueva el orden se invierte (las 17 migraciones corren sobre tablas
vacías y no transforman nada), así que el `.sql` termina chocando contra un
esquema que ya no tiene `clientes.localidad` ni admite `guias.destinatario_id`
nulo. Si alguna vez hay que rehacer la carga desde los `.DBF`, el camino es
reproducir local la secuencia migrar→importar→normalizar y volver a dumpear.

### Por qué los assets se compilan acá y no en el VPS

En el VPS hay Node 22.16 instalado por nvm, pero fuera del `PATH` de un ssh no
interactivo (nvm se carga desde `.bashrc`), así que un script ve el Node 18 del
sistema. Se podría arreglar anteponiendo el `bin/` de nvm al `PATH`, pero no
conviene:

- el build entero pesa **205 KB** (130 KB comprimido); compilarlo allá ahorra eso
  y cuesta un `npm ci` de cientos de MB en un server con **~1 GB de RAM libre**;
- si el build falla, compilando acá falla antes de tocar el servidor; compilando
  allá falla **después** del `git reset --hard`, con el sitio en mantenimiento;
- el build sale a internet (`vite.config.js` baja Instrument Sans de
  `fonts.bunny.net`), y eso es una dependencia más en el camino crítico del deploy.

Con `ASSETS_ORIGEN=local` (el default) se compila tu working tree, que gracias al
commit automático es idéntico a `main`. `ASSETS_ORIGEN=main` clona la rama en un
temporal y hace `npm ci` + build ahí: más lento, pero inmune a un `node_modules`
local desactualizado.

---

## Detalles que conviene saber

**Se despliega lo que está en GitHub**, pero como el commit automático pushea
antes, eso es siempre lo que tenés acá. Con `--sin-commit` vuelve a ser tu
responsabilidad.

**La contraseña de la base.** Si `DB_PASSWORD` queda vacía, el setup genera una
al azar, la escribe en el `.env` del servidor y la imprime **una sola vez** al
final. En corridas posteriores la reutiliza leyéndola del `.env`.

**Los datos legacy se importan una sola vez.** Si la tabla `guias` ya tiene
registros, el setup no vuelve a volcar el `.sql` salvo que le pases
`--forzar-datos`.

**Todo corre como root en el servidor** y al final se hace
`chown -R www-data:www-data`, así no quedan logs ni cachés que PHP-FPM no pueda
escribir. Si corrés algo a mano en el VPS, acordate de repetir ese chown (o usá
`./artisan.sh`, que ya lo hace).

**Certbot y el vhost.** La primera vez el vhost se escribe desde
`templates/nginx.conf.tpl` y después certbot lo modifica para agregar el 443. A
partir de ahí el setup **no lo pisa** — si necesitás regenerarlo:
`./setup.sh --forzar-nginx` (queda un `.bak` con fecha al lado).

**Usuarios iniciales.** Los crea `UserSeeder` con las contraseñas de
`credenciales/usuarios.md`. **Cambiarlas apenas entres**: ese archivo estuvo
versionado hasta el commit `faf402e`, así que las contraseñas siguen siendo
recuperables de la historia del repo aunque el archivo ya no esté en la punta.

---

## Si algo sale mal

```bash
ssh mivps

tail -f /var/log/nginx/paqueteria.codeland.com.ar.error.log
tail -f /var/www/paqueteria/app/storage/logs/laravel.log
journalctl -u paqueteria-queue -n 50
systemctl status php8.4-fpm nginx
nginx -t
```

| Síntoma | Causa típica |
|---|---|
| 502 Bad Gateway | PHP-FPM caído, o `PHP_FPM_PASS` mal (`127.0.0.1:19000`) |
| 500 en blanco | permisos de `storage/` — corré `./deploy.sh` de nuevo |
| Página sin estilos | faltó subir `public/build` — `./deploy.sh` sin `--sin-assets` |
| El sitio quedó "en mantenimiento" | `./artisan.sh up` |
| certbot falla | el DNS de `paqueteria.codeland.com.ar` no apunta al VPS todavía |
| "dubious ownership" en git | `./artisan.sh` y después `git config --global --add safe.directory /var/www/paqueteria` |

Para volver atrás un deploy: `./artisan.sh` y desde el VPS
`git -C /var/www/paqueteria reset --hard <commit>`, o directamente desplegar el
commit anterior con `REPO_BRANCH=<rama-o-tag> ./deploy.sh`.
