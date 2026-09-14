# syntax=docker/dockerfile:1

# ---------------------------------------------------------------
# Etapa 1: compilación de assets (Vite)
# ---------------------------------------------------------------
FROM node:20-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build

# ---------------------------------------------------------------
# Etapa 2: dependencias PHP (Composer, sin dev)
# ---------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ---------------------------------------------------------------
# Etapa 3: imagen de ejecución
# ---------------------------------------------------------------
FROM php:8.3-cli-alpine AS app

ENV APP_ENV=production \
    APP_DEBUG=false \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apk add --no-cache \
        bash \
        postgresql-client \
        postgresql-libs \
        libpng \
        libjpeg-turbo \
        freetype \
        libzip \
        icu-libs \
        oniguruma \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        postgresql-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        gd \
        zip \
        intl \
        bcmath \
        opcache \
        mbstring \
    && apk del .build-deps

# Configuración de OPcache y PHP para producción
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
    } > "$PHP_INI_DIR/conf.d/opcache.ini" \
    && { \
        echo 'upload_max_filesize=20M'; \
        echo 'post_max_size=25M'; \
        echo 'memory_limit=256M'; \
    } > "$PHP_INI_DIR/conf.d/odontosuite.ini"

# Usuario no root
RUN addgroup -g 1000 -S odonto \
    && adduser -u 1000 -S -G odonto -h /var/www odonto

WORKDIR /var/www/html

# Código de la aplicación
COPY --chown=odonto:odonto . .

# Dependencias y assets compilados desde las etapas anteriores
COPY --from=vendor --chown=odonto:odonto /app/vendor ./vendor
COPY --from=assets --chown=odonto:odonto /app/public/build ./public/build

# Composer (sólo para regenerar el autoloader con el código de la app)
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Entrypoint
COPY --chown=odonto:odonto docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-scripts --no-interaction \
    && chown -R odonto:odonto storage bootstrap/cache vendor

USER odonto

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
