FROM php:8.3-fpm-alpine

# Systempakete und Build-Dependencies
RUN apk add --no-cache \
        bash \
        git \
        unzip \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        postgresql-dev \
        mysql-client \
        linux-headers \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS

# PHP Extensions
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        intl \
        mbstring \
        pdo \
        pdo_mysql \
        zip \
        gd \
        opcache

# Optional: falls du PostgreSQL statt MySQL nutzt
RUN docker-php-ext-install pdo_pgsql

# Composer aus offiziellem Image übernehmen
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Arbeitsverzeichnis
WORKDIR /var/www/html

# Erst composer-Dateien kopieren für besseres Docker-Caching
COPY composer.json composer.lock* ./

# Dependencies installieren
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress || true

# Restliche Applikation kopieren
COPY . .

# CakePHP-Verzeichnisse vorbereiten
# app selbst bleibt readonly - security
# nur tmp und logs sind schreibbar vom www-data:www-data user

#RUN mkdir -p \
#        tmp \
#        logs \
#    && chown -R www-data:www-data /var/www/html \
#    && chmod -R 775 tmp logs
RUN mkdir -p tmp logs && chown -R www-data:www-data tmp logs && chmod 775 tmp logs
RUN rm -rf tmp/*
RUN rm -rf logs/*

# Nochmal Composer ausführen, falls beim ersten Schritt Dateien gefehlt haben
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress

# PHP-Konfiguration (optional sinnvoll für Produktion)
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
    } > /usr/local/etc/php/conf.d/app.ini

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
