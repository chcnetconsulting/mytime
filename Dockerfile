FROM php:8.3-fpm-alpine AS php-base

# libpq ist die Laufzeitbibliothek hinter pdo_pgsql und MUSS im Image bleiben —
# ohne sie startet php-fpm mit "Unable to load dynamic library pdo_pgsql".
# libpq-dev braucht nur der Build und wandert deshalb in .build-deps.
#
# mysql-client und pdo_mysql bleiben nur bis zum Abbau von mysql-0: das
# einmalige Import-Kommando (bin/cake import_mysql) spricht beide Datenbanken
# im selben Prozess an. Danach fallen sie mit Image 2.2.1 weg.
RUN apk add --no-cache \
        bash \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libpq \
        postgresql-client \
        mysql-client \
        linux-headers \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        mbstring \
        pdo_pgsql \
        pdo_mysql \
        zip \
        gd \
        opcache \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

FROM php-base AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader

FROM php-base AS runtime

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --chown=www-data:www-data config/app_local.docker.php ./config/app_local.php

RUN composer dump-autoload \
        --no-dev \
        --classmap-authoritative \
        --no-interaction \
    && mkdir -p tmp logs \
    && rm -rf tmp/* logs/* \
    && chown -R www-data:www-data tmp logs \
    && chmod -R 775 tmp logs

RUN { \
        echo "memory_limit=256M"; \
        echo "upload_max_filesize=25M"; \
        echo "post_max_size=25M"; \
        echo "max_execution_time=60"; \
        echo "opcache.enable=1"; \
        echo "opcache.enable_cli=1"; \
        echo "opcache.validate_timestamps=0"; \
        echo "opcache.memory_consumption=128"; \
        echo "opcache.interned_strings_buffer=16"; \
        echo "opcache.max_accelerated_files=10000"; \
        echo "realpath_cache_size=4096K"; \
        echo "realpath_cache_ttl=600"; \
    } > /usr/local/etc/php/conf.d/mytime.ini

USER www-data

EXPOSE 9000

CMD ["php-fpm", "-F"]
