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
FROM php:8.4-apache-bookworm AS app

ENV APP_ENV=production \
    APP_DEBUG=false \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libzip-dev \
        postgresql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        gd \
        zip \
        intl \
        bcmath \
        opcache \
        mbstring \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

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

WORKDIR /var/www/html

# Código de la aplicación
COPY --chown=www-data:www-data . .

# Dependencias y assets compilados desde las etapas anteriores
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# Composer (sólo para regenerar el autoloader con el código de la app)
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Entrypoint
COPY --chown=www-data:www-data docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-scripts --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache vendor \
    && sed -ri 's!Listen 80!Listen 8000!g' /etc/apache2/ports.conf

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl --fail --silent http://127.0.0.1:8000/up >/dev/null || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
