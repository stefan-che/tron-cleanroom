# PHP 8.4: composer.lock pins symfony/* v8 (php >= 8.4.1); bookworm like the prod image (trixie has no software-properties-common).
FROM php:8.4-fpm-bookworm

RUN mkdir -p /var/www

RUN apt-get update && apt-get install -y \
        nano \
        curl \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        libxml2-dev \
        libgmp-dev \
        zip \
        libmagickwand-dev \
        git \
        software-properties-common \
        supervisor && \
    docker-php-ext-install -j$(nproc) iconv pdo pdo_mysql pdo_pgsql pgsql bcmath intl zip soap gmp && \
    apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

#RUN npm install npm@latest -g && \
#    npm install n -g && \
#    n latest

#RUN pecl install imagick \
#    && docker-php-ext-enable imagick

# decimal
RUN apt-get update && apt-get install -y wget build-essential \
    && wget https://www.bytereef.org/software/mpdecimal/releases/mpdecimal-2.5.1.tar.gz \
    && tar -xvf mpdecimal-2.5.1.tar.gz \
    && cd mpdecimal-2.5.1 \
    && ./configure \
    && make \
    && make install \
    && cd .. \
    && rm -rf mpdecimal-2.5.1 mpdecimal-2.5.1.tar.gz \
    && pecl install decimal \
    && docker-php-ext-enable decimal


# 3.8.0 is the first release that builds on PHP 8.4.
ARG IMAGICK_VERSION=3.8.0
#https://gist.github.com/Wirone/d5c794b4fef0203146a27687e80588a6
# Imagick is installed from the archive because regular installation fails
# See: https://github.com/Imagick/imagick/issues/643#issuecomment-1834361716
RUN curl -L -o /tmp/imagick.tar.gz https://github.com/Imagick/imagick/archive/refs/tags/${IMAGICK_VERSION}.tar.gz \
    && tar --strip-components=1 -xf /tmp/imagick.tar.gz \
    && phpize \
    && ./configure \
    && make \
    && make install \
    && echo "extension=imagick.so" > /usr/local/etc/php/conf.d/ext-imagick.ini \
    && rm -rf /tmp/*
    # <<< End of Imagick installation

RUN mkdir -p /var/log/supervisor

RUN mkdir -p /var/run/supervisor && \
    chmod 777 /var/run/supervisor

RUN pecl install -o -f redis \
    &&  rm -rf /tmp/pear \
    &&  docker-php-ext-enable redis

WORKDIR /var/www/app

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

CMD ["sh", "-c", "php-fpm & /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf"]
