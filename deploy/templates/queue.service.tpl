# Worker de colas de {{APP_NAME}} ({{DOMINIO}})
# Generado por deploy/setup.sh — systemd unit
[Unit]
Description=Cola de {{APP_NAME}} ({{DOMINIO}})
After=network.target mysql.service

[Service]
User={{WEB_USER}}
Group={{WEB_GROUP}}
Restart=always
RestartSec=5
WorkingDirectory={{APP_DIR}}
ExecStart=/usr/bin/{{PHP_BIN}} {{APP_DIR}}/artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600
StandardOutput=append:/var/log/{{SERVICIO_QUEUE}}.log
StandardError=append:/var/log/{{SERVICIO_QUEUE}}.log

[Install]
WantedBy=multi-user.target
