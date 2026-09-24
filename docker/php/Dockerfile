FROM php:8.4-fpm-alpine

# Set working directory
WORKDIR /var/www

# Install system dependencies and PECL extensions
RUN apk add --no-cache \
        linux-headers \
        autoconf \
        gcc \
        g++ \
        make \
        postgresql-dev \
    && pecl install xdebug \
    && pecl install --onlyreqdeps --force redis \
    && docker-php-ext-enable xdebug redis \
    && docker-php-ext-install \
        pdo_pgsql \
        bcmath \
    && rm -rf /tmp/pear

# Add PHP config
COPY docker/php/conf.d/ $PHP_INI_DIR/conf.d/

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

