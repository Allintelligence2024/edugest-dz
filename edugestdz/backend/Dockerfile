# backend/Dockerfile
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# STAGE 1 : Build des dépendances PHP
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
FROM php:8.3-fpm-alpine AS base

LABEL maintainer="EduGest DZ Team"
LABEL version="1.0.0"

# ── Extensions PHP nécessaires ──
RUN apk add --no-cache \
    bash \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    oniguruma-dev \
    postgresql-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    icu-dev \
    shadow \
    supervisor

# ── Installer les extensions PHP ──
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        xml \
        intl \
        opcache \
        sockets

# ── Installer Redis Extension ──
RUN pecl install redis && docker-php-ext-enable redis

# ── Installer Composer ──
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# ── Configuration PHP ──
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# ── Répertoire de travail ──
WORKDIR /var/www/html

# ── Copier les fichiers composer ──
COPY composer.json composer.lock ./

# ── Installer les dépendances sans scripts ──
RUN composer install \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --no-dev

# ── Copier tout le code source ──
COPY . .

# ── Permissions ──
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# ── Exécuter les scripts Composer ──
RUN composer dump-autoload --optimize

EXPOSE 9000

CMD ["php-fpm"]

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# STAGE 2 : Image de développement (avec outils dev)
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
FROM base AS development

RUN composer install --no-interaction

# Xdebug pour le débogage
RUN pecl install xdebug && docker-php-ext-enable xdebug
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
