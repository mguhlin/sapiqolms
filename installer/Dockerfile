# Sapiqo — turnkey container. Bundles every PHP extension (gd, zip, pdo_mysql,
# pdo_sqlite, mbstring, curl, dom/simplexml/xml, phar, openssl, fileinfo, sodium)
# so there is nothing to install on the host.  Build context = the courses/ root.
FROM php:8.3-apache

# Security — refuse to build on a PHP vulnerable to PHP2Shell (CVE-2026-109618/-109619).
# `docker build --pull` so the floating 8.3 tag resolves to the latest patch release.
RUN php -r 'exit(version_compare(PHP_VERSION, "8.3.36", ">=") ? 0 : 1);' \
    || { echo "PHP $(php -r 'echo PHP_VERSION;') is vulnerable to PHP2Shell; rebuild with --pull" >&2; exit 1; }

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libonig-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" gd zip pdo_mysql mbstring; \
    a2enmod rewrite headers; \
    rm -rf /var/lib/apt/lists/*
# pdo_sqlite, curl, dom, simplexml, xml, phar, openssl, fileinfo, sodium, zlib, json
# are already compiled into the official php:8.3 image.

# App layout inside the image: /srv/sapiqo (code) + /srv/content (courses).
# Data lives on a volume at /data (SAPIQO_DATA), so it persists across upgrades.
ENV SAPIQO_DATA=/data
COPY installer/docker/uploads.ini /usr/local/etc/php/conf.d/sapiqo-uploads.ini
WORKDIR /srv
COPY sapiqo/  /srv/sapiqo/
COPY content/ /srv/content/

# Apache document root = the LMS public/ folder.
RUN rm -rf /var/www/html && ln -s /srv/sapiqo/public /var/www/html
COPY installer/docker/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY installer/docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
