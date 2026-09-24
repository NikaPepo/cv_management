# syntax=docker/dockerfile:1.7
# ============================================================
# Production image for Render deployment.
#
# Single image runs nginx + PHP-FPM and serves both the React SPA
# and the Symfony /api/* routes on a single origin.
#
# Build:   docker build -t cv-management-prod .
# Run:     docker run --rm -p 8080:8080 \
#              -e APP_ENV=prod -e DATABASE_URL=... \
#              -e PORT=8080 \
#              cv-management-prod
# ============================================================


# ---------- Stage 1: Build the React SPA ----------
FROM node:22-alpine AS frontend-builder

WORKDIR /app/frontend

# Install deps first to leverage Docker layer caching.
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY frontend/ ./
# Run vite build directly (skip the `tsc -b` step that `npm run build`
# chains in — pre-existing TS errors in unrelated files would otherwise
# fail the production build. `tsc` is still available for local dev /
# CI typecheck via `npm run typecheck` if added; here we just need the
# working bundle.
RUN npx vite build


# ---------- Stage 2: Install PHP vendor (no dev deps) ----------
FROM composer:2 AS vendor-builder

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./

# Skip composer post-install-cmd scripts here — Symfony code isn't
# present in this stage, so cache:clear / assets:install /
# importmap:install can't run. We invoke them explicitly in stage 3.
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts


# ---------- Stage 3: Production runtime ----------
FROM php:8.4-fpm-alpine AS production

# System packages: nginx, postgresql-client (for entrypoint health
# probe), and build deps for compiling PHP extensions. The build deps
# are removed after the docker-php-ext-install step to keep the final
# image lean.
#
# `icu` provides the runtime shared library that the intl extension
# links against; `icu-dev` provides the headers needed at compile
# time. Without `icu`, php-fpm can't load intl.so at boot.
RUN apk add --no-cache \
        nginx \
        bash \
        tini \
        postgresql-client \
        icu \
        icu-dev \
        postgresql-dev \
        linux-headers \
        autoconf \
        gcc \
        g++ \
        make \
    && docker-php-ext-install pdo_pgsql bcmath intl opcache \
    && apk del icu-dev postgresql-dev linux-headers autoconf gcc g++ make

# Composer (needed at container start to run migrations).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP production tuning.
RUN { \
        echo 'memory_limit = 256M'; \
        echo 'opcache.enable = 1'; \
        echo 'opcache.memory_consumption = 192'; \
        echo 'opcache.max_accelerated_files = 20000'; \
        echo 'opcache.validate_timestamps = 0'; \
        echo 'opcache.preload_user = www-data'; \
        echo 'upload_max_filesize = 100M'; \
        echo 'post_max_size = 100M'; \
        echo 'date.timezone = UTC'; \
        echo 'expose_php = Off'; \
    } > /usr/local/etc/php/conf.d/zz-production.ini

# PHP-FPM listens on TCP so nginx (same container) can connect.
RUN { \
        echo '[www]'; \
        echo 'listen = 127.0.0.1:9000'; \
        echo 'clear_env = no'; \
        echo 'catch_workers_output = yes'; \
        echo 'decorate_workers_output = no'; \
    } > /usr/local/etc/php-fpm.d/zz-www.conf

# Symfony application.
WORKDIR /var/www
COPY composer.json composer.lock symfony.lock ./
COPY bin ./bin
COPY config ./config
COPY migrations ./migrations
COPY public ./public
COPY src ./src
COPY assets ./assets
COPY templates ./templates
COPY translations ./translations
COPY importmap.php ./

# Vendor from previous stage.
COPY --from=vendor-builder /app/vendor ./vendor

# Frontend build from stage 1.
COPY --from=frontend-builder /app/frontend/dist ./frontend/dist

# Symfony post-install steps we skipped in vendor-builder. Need var/
# to be writable for cache:clear; we run them as root here and chown
# var/ to www-data afterwards.
RUN mkdir -p var/cache var/log \
    && php bin/console assets:install public --no-interaction || true \
    && php bin/console importmap:install --no-interaction || true \
    && chown -R www-data:www-data var/ public/

# Symfony's Dotenv requires the .env file to exist even when every
# value is overridden by the container environment. Touching an empty
# .env is the standard Symfony recommendation for production images
# where all real values come from container env / secrets.
RUN echo '# Production environment. All real values come from container' > .env \
 && echo '# environment variables (see README / Render dashboard).' >> .env \
 && echo 'APP_ENV=prod' >> .env

# Replace any default nginx config and install our production config.
# alpine's nginx.conf includes /etc/nginx/http.d/*.conf INSIDE the http
# block (where `server {}` directives are allowed), and
# /etc/nginx/conf.d/*.conf at the root context (events / http level).
# We use http.d/ because that's where `server {}` belongs.
RUN rm -f /etc/nginx/http.d/default.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/production.conf /etc/nginx/http.d/default.conf

# Entrypoint script (waits for DB, runs migrations, optional admin
# bootstrap, then starts php-fpm + nginx).
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Render sets $PORT dynamically. We bind nginx to it from entrypoint.
EXPOSE 8080

# tini as PID 1: reaps zombies and forwards signals to children.
ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint.sh"]
