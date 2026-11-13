# syntax=docker/dockerfile:1
#
# Production PHP image: php-fpm + CLI (queues, scheduler, artisan).
# Code is NOT copied inside: it comes in via bind mount, as agreed
# for the "git pull on the server" deploy model.

# PHP 8.4: composer.lock pins symfony/* v8, which require php >= 8.4.1.
FROM php:8.4-fpm-bookworm

ARG PUID=33
ARG PGID=33

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    COMPOSER_MEMORY_LIMIT=-1

# --- Runtime system packages ------------------------------------------------
# Only what the running container needs:
#   libfcgi-bin — healthcheck php-fpm (cgi-fcgi),
#   procps      - pgrep in the scheduler healthcheck,
#   gosu        - privilege drop in the entrypoint,
#   supervisor  - queue worker master,
#   unzip       - fallback archive extractor for composer.
#
# git is NOT installed: `git pull` runs on the host (see deploy.sh), and all 102
# dependencies in composer.lock come as dist archives (type: zip), so
# composer install --prefer-dist works without it. This also removes
# perl, libperl5.36 and perl-modules from the image: ~90 MB and their CVEs.
#
# apt-get upgrade pulls Debian security updates released after the
# base image was built: without it trivy reports already fixed CVEs
# in libexpat, libssh2, libpcre2 and others.
ENV RUNTIME_DEPS="ca-certificates curl gosu libfcgi-bin procps supervisor unzip"

RUN set -eux; \
    apt-get update; \
    apt-get upgrade -y --no-install-recommends; \
    apt-get install -y --no-install-recommends ${RUNTIME_DEPS}; \
    rm -rf /var/lib/apt/lists/*

# --- PHP extensions ---------------------------------------------------------
# pcntl and sockets are required for queue:work to behave correctly (timeouts, signals).
#
# Header packages (-dev) and the compiler are needed only here, so they are installed
# and removed within ONE layer, otherwise they stay in the image history and
# the size does not shrink. The removal drops linux-libc-dev (63 HIGH in trivy,
# kernel headers, no executable code), libxml2-dev, libssl-dev, libicu-dev,
# gcc/g++/binutils: about 250 MB in total.
#
# The removal scheme is the standard one for official php images: before installing,
# record the list of "manual" packages; after the build, mark everything
# as automatic, mark back as manual the runtime set and the libraries
# actually referenced by the built .so files and php itself (via ldd), and remove the rest
# with `--auto-remove`. Without ldd, auto-remove would take libicu72, libpq5 and
# other runtime libraries along with their -dev packages.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libgmp-dev \
        libicu-dev \
        libpq-dev \
        libxml2-dev \
        libzip-dev \
    ; \
    docker-php-ext-configure intl; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gmp \
        intl \
        opcache \
        pcntl \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        pgsql \
        soap \
        sockets \
        zip \
    ; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    rm -rf /tmp/pear; \
    \
    apt-mark auto '.*' > /dev/null; \
    apt-mark manual ${RUNTIME_DEPS} passwd > /dev/null; \
    find /usr/local/lib/php/extensions /usr/local/bin/php /usr/local/sbin/php-fpm \
            -type f -exec ldd '{}' ';' 2>/dev/null \
        | awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) next; gsub("^/(usr/)?", "", so); printf "*/%s\n", so }' \
        | sort -u \
        | xargs -r dpkg-query --search 2>/dev/null \
        | cut -d: -f1 \
        | sort -u \
        | xargs -r apt-mark manual > /dev/null; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    rm -rf /var/lib/apt/lists/*; \
    \
    php -v; \
    php -m

# sqlite3 and pdo_sqlite are not used by the application (DBs are MySQL and PostgreSQL),
# but they cannot be disabled: the official php image compiles them into the binary
# instead of loading them via a separate ini. So libsqlite3-0 stays in the image.

# Heavy extensions from the local image (imagick, decimal) are intentionally
# not installed: app/ does not use them, and building them takes minutes and pulls
# ~200 MB of dependencies.

# --- Composer ---------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# --- Configuration ----------------------------------------------------------
COPY ./images/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY ./images/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY ./images/php/php-fpm-healthcheck /usr/local/bin/php-fpm-healthcheck
COPY ./images/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/php-fpm-healthcheck /usr/local/bin/entrypoint.sh

# --- User -------------------------------------------------------------------
# UID/GID are matched to the owner of the host files so that storage and
# bootstrap/cache are writable without chmod 777.
RUN set -eux; \
    if [ "$(id -u www-data)" != "${PUID}" ]; then usermod -u "${PUID}" www-data; fi; \
    if [ "$(id -g www-data)" != "${PGID}" ]; then groupmod -g "${PGID}" www-data; fi; \
    mkdir -p /var/log/supervisor /var/run/supervisor /tmp/composer; \
    chown -R www-data:www-data /var/log/supervisor /var/run/supervisor /tmp/composer

WORKDIR /var/www/app

# The production container must not be able to install dev dependencies or listen for xdebug.
ENV APP_ENV=production

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]
