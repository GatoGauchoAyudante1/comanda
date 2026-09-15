# ------------------------------------------------------------------------------
# {{DOMINIO}} — Transporte Elisa (Laravel)
# Generado por deploy/setup.sh a partir de deploy/templates/nginx.conf.tpl
# NO editar a mano: si certbot todavía no tocó este archivo, el próximo setup
# lo pisa. Para cambios permanentes, editar la plantilla.
# ------------------------------------------------------------------------------

server {
    listen 80;
    listen [::]:80;

    server_name {{SERVER_NAMES}};
    root {{ROOT_PUBLICO}};

    index index.php index.html;
    charset utf-8;

    client_max_body_size {{UPLOAD_MAX}};

    access_log /var/log/nginx/{{DOMINIO}}.access.log;
    error_log  /var/log/nginx/{{DOMINIO}}.error.log;

    # Seguridad básica
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Compresión
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml application/javascript application/json
               application/xml image/svg+xml font/woff font/woff2;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # Assets compilados por Vite: nombre con hash, se pueden cachear fuerte.
    location ^~ /build/ {
        expires 1y;
        access_log off;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    location ~* \.(?:css|js|woff2?|ttf|eot|svg|png|jpe?g|gif|webp|ico)$ {
        expires 30d;
        access_log off;
        add_header Cache-Control "public";
        try_files $uri =404;
    }

    location ~ \.php$ {
        fastcgi_pass {{PHP_FPM_PASS}};
        fastcgi_index index.php;
        fastcgi_read_timeout 300;

        # El fastcgi_params de este VPS está customizado: ya define
        # fastcgi_buffers / fastcgi_buffer_size (repetirlos es [emerg] duplicate)
        # y setea SCRIPT_FILENAME a $request_filename. Por eso el include va
        # PRIMERO y lo nuestro después, para que gane.
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param HTTP_PROXY "";
    }

    # Nada de dotfiles ni de meterse fuera de public/
    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~ ^/(storage/framework|bootstrap/cache|vendor)/ {
        deny all;
    }

    error_page 404 /index.php;
}
