#!/bin/sh
# Prepara carpetas de evidencia y genera la configuración de BD a partir del entorno.
set -e

for d in web app db fim; do mkdir -p "/var/log/techcorp/$d"; done
# La carpeta ./logs del anfitrión debe poder escribirla Apache (root) y PHP (www-data).
chmod 0777 /var/log/techcorp /var/log/techcorp/web /var/log/techcorp/app \
           /var/log/techcorp/db /var/log/techcorp/fim 2>/dev/null || true
# Los logs se crean de antemano con 0666 para que PHP (www-data) pueda recortarlos a la línea
# base cuando el notebook del ataque en vivo reinicia el escenario (ver lab/linea_base.php).
for f in web/access.jsonl app/app.jsonl db/audit.jsonl fim/fim.jsonl; do
    touch "/var/log/techcorp/$f" && chmod 0666 "/var/log/techcorp/$f" 2>/dev/null || true
done

mkdir -p /var/www/html/uploads/avatars /var/www/html/config /var/lib/techcorp-lab
chown -R www-data:www-data /var/www/html/uploads /var/lib/techcorp-lab

# MALA PRÁCTICA INTENCIONAL DEL ESCENARIO: credenciales en texto plano dentro del docroot,
# incluida la cuenta de mantenimiento con todos los privilegios. Es lo que el atacante lee.
cat > /var/www/html/config/database.php <<PHP
<?php
// Configuración de base de datos — Portal interno TechCorp (generado al arrancar)
return [
    'host'   => '${DB_HOST:-db}',
    'nombre' => '${DB_NAME:-techcorp}',
    'app'    => ['user' => '${DB_APP_USER:-techcorp_app}', 'pass' => '${DB_APP_PASSWORD}'],
    // TODO (2023): retirar. Cuenta de mantenimiento usada por los scripts viejos de /legacy
    'admin'  => ['user' => '${DB_ADMIN_USER:-techcorp_admin}', 'pass' => '${DB_ADMIN_PASSWORD}'],
];
PHP
chown root:www-data /var/www/html/config/database.php
chmod 0640 /var/www/html/config/database.php

exec docker-php-entrypoint "$@"
