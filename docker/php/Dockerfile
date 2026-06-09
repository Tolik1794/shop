FROM php:8.4-fpm-alpine

COPY --from=ghcr.io/symfony-cli/symfony-cli:latest /usr/local/bin/symfony /usr/local/bin/symfony

RUN apk add --no-cache \
        bash \
        git \
        icu-dev \
        libpng-dev \
        libzip-dev \
        nodejs \
        npm \
        postgresql-dev \
        unzip \
        yarn \
        zip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        intl \
        opcache \
        pdo_pgsql \
        zip \
    && pecl install redis xdebug \
    && docker-php-ext-enable redis xdebug \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && { \
        echo "memory_limit=512M"; \
        echo "date.timezone=Europe/Kiev"; \
        echo "opcache.enable=1"; \
        echo "opcache.enable_cli=1"; \
      } > "$PHP_INI_DIR/conf.d/shop.ini"

COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini

WORKDIR /var/www/shop
