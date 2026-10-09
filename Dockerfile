# syntax = docker/dockerfile:1.7
FROM dunglas/frankenphp:1-php8.3-alpine AS builder

WORKDIR /app

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    git \
    nodejs \
    npm \
    postgresql-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    linux-headers \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_pgsql \
    pdo_mysql \
    gd \
    intl \
    zip \
    opcache \
    bcmath \
    pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del postgresql-dev libpng-dev libjpeg-turbo-dev freetype-dev icu-dev libzip-dev oniguruma-dev linux-headers $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install PHP dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts

# Production stage
FROM dunglas/frankenphp:1-php8.3-alpine

WORKDIR /app

# Install runtime dependencies
RUN apk add --no-cache \
    postgresql-libs \
    libpng \
    libjpeg-turbo \
    freetype \
    icu-libs \
    libzip \
    oniguruma \
    && apk add --no-cache --virtual .build-deps \
    postgresql-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_pgsql \
    pdo_mysql \
    gd \
    intl \
    zip \
    opcache \
    bcmath \
    pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy vendor from builder
COPY --from=builder /app/vendor ./vendor

# Copy application code
COPY . .

# Create necessary directories and set permissions
RUN mkdir -p \
    storage/framework/{views,sessions,cache} \
    storage/logs \
    storage/app/{invoices,temp,public} \
    bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# OPcache + JIT configuration
RUN echo "opcache.enable=1\nopcache.memory_consumption=64\nopcache.interned_strings_buffer=16\nopcache.max_accelerated_files=10000\nopcache.revalidate_freq=0\nopcache.jit_buffer_size=32M\nopcache.jit=1255" > /usr/local/etc/php/conf.d/opcache.ini

# Clear and cache configuration
RUN php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan filament:optimize

# Create storage link
RUN php artisan storage:link

ENV APP_ENV=production
ENV FRANKENPHP_CONFIG="/etc/frankenphp/Caddyfile"

EXPOSE 8000

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]