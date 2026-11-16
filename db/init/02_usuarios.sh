#!/bin/bash
# Cuentas de base de datos del laboratorio (las contraseñas llegan por variables de entorno).
#  - techcorp_app   : la que usa el portal en su operación normal (mínimo privilegio).
#  - techcorp_admin : cuenta de mantenimiento heredada con todos los privilegios. Su contraseña
#                     quedó escrita en config/database.php (mala práctica intencional del escenario).
#  - forense_ro     : cuenta de SOLO LECTURA para el analista forense (notebook).
set -euo pipefail

mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE USER IF NOT EXISTS 'techcorp_app'@'%'   IDENTIFIED BY '${DB_APP_PASSWORD}';
GRANT SELECT, INSERT, UPDATE ON techcorp.* TO 'techcorp_app'@'%';

CREATE USER IF NOT EXISTS 'techcorp_admin'@'%' IDENTIFIED BY '${DB_ADMIN_PASSWORD}';
GRANT ALL PRIVILEGES ON techcorp.* TO 'techcorp_admin'@'%';

CREATE USER IF NOT EXISTS 'forense_ro'@'%'     IDENTIFIED BY '${DB_FORENSE_PASSWORD}';
GRANT SELECT ON techcorp.* TO 'forense_ro'@'%';

FLUSH PRIVILEGES;
SQL
