# Portal interno de TechCorp: PHP 8.3.30 + Apache (mod_php).
# PHP 8.3.30 es ANTERIOR al parche 8.3.36 que corrige PHP2Shell (CVE-2026-109618/109619).
# En este laboratorio NO se explota la falla real: el comportamiento del ataque se SIMULA
# (ver lab/simulador_php2shell.php) para dejar una huella forense realista en los logs.
FROM php:8.3.30-apache

RUN docker-php-ext-install -j"$(nproc)" pdo_mysql opcache
# mod_logio (bytes enviados/recibidos) ya viene compilado dentro de Apache en Debian.
RUN a2enmod rewrite headers remoteip unique_id \
 && a2dissite 000-default \
 && a2disconf other-vhosts-access-log

COPY apache/techcorp.conf /etc/apache2/sites-available/techcorp.conf
COPY php/techcorp.ini     /usr/local/etc/php/conf.d/zz-techcorp.ini
COPY lab/                 /opt/techcorp-lab/
COPY www/                 /var/www/html/
COPY entrypoint.sh        /usr/local/bin/techcorp-entrypoint

RUN a2ensite techcorp \
 && chmod +x /usr/local/bin/techcorp-entrypoint \
 && mkdir -p /var/lib/techcorp-lab \
 && chown -R www-data:www-data /var/lib/techcorp-lab /var/www/html/uploads

ENTRYPOINT ["techcorp-entrypoint"]
CMD ["apache2-foreground"]
