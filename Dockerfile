# syntax = docker/dockerfile:experimental

ARG PHP_VERSION=8.2
ARG NODE_VERSION=18
FROM dunglas/frankenphp:php${PHP_VERSION}-bookworm as base
LABEL fly_launch_runtime="laravel"

# PHP_VERSION needs to be repeated here
# See https://docs.docker.com/engine/reference/builder/#understand-how-arg-and-from-interact
ARG PHP_VERSION
ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/composer \
    COMPOSER_MAX_PARALLEL_HTTP=24

# Prepare base container:
# 1. Install PHP, Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends gnupg2 ca-certificates git-core curl zip unzip \
                                                  rsync vim-tiny htop sqlite3 nginx supervisor cron \
    && ln -sf /usr/bin/vim.tiny /etc/alternatives/vim \
    && ln -sf /etc/alternatives/vim /usr/bin/vim \
    && apt-get update \
    && install-php-extensions pdo_pgsql pgsql \
    && mkdir -p /var/www/html/public && echo "index" > /var/www/html/public/index.php \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/* \
    && rm -f /usr/local/etc/php/conf.d/docker-php-ext-pcntl.ini

# 2. Copy config files to proper locations
COPY .fly/supervisor/ /etc/supervisor/
COPY .fly/entrypoint.sh /entrypoint

# 3. Copy application code, skipping files based on .dockerignore
COPY . /var/www/html
RUN mv /var/www/html/public/index_worker.php /var/www/html/public/index.php
WORKDIR /var/www/html

# 4. Setup application dependencies
RUN composer install --optimize-autoloader --no-dev \
    && mkdir -p storage/logs \
    && frankenphp php-cli artisan optimize:clear \
    && chown -R www-data:www-data /var/www/html \
    && echo "MAILTO=\"\"\n* * * * * www-data /usr/bin/php /var/www/html/artisan schedule:run" > /etc/cron.d/laravel \
    && sed -i='' '/->withMiddleware(function (Middleware \$middleware) {/a\
        \$middleware->trustProxies(at: "*");\
    ' bootstrap/app.php; \
    if [ -d .fly ]; then cp .fly/entrypoint.sh /entrypoint; chmod +x /entrypoint; fi;

# Multi-stage build: Build static assets
# This allows us to not include Node within the final container
FROM node:${NODE_VERSION} as node_modules_go_brrr

RUN mkdir -p  /app
WORKDIR /app
COPY . .
COPY --from=base /var/www/html/vendor /app/vendor

# Use yarn or npm depending on what type of
# lock file we might find. Defaults to
# NPM if no lock file is found.
# Note: We run "production" for Mix and "build" for Vite
RUN if [ -f "vite.config.js" ]; then \
        ASSET_CMD="build"; \
    else \
        ASSET_CMD="production"; \
    fi; \
    if [ -f "yarn.lock" ]; then \
        yarn install --frozen-lockfile; \
        yarn $ASSET_CMD; \
    elif [ -f "pnpm-lock.yaml" ]; then \
        corepack enable && corepack prepare pnpm@latest-8 --activate; \
        pnpm install --frozen-lockfile; \
        pnpm run $ASSET_CMD; \
    elif [ -f "package-lock.json" ]; then \
        npm ci --no-audit; \
        npm run $ASSET_CMD; \
    else \
        npm install; \
        npm run $ASSET_CMD; \
    fi;

# From our base container created above, we
# create our final image, adding in static
# assets that we generated above
FROM base

# Packages like Laravel Nova may have added assets to the public directory
# or maybe some custom assets were added manually! Either way, we merge
# in the assets we generated above rather than overwrite them
COPY --from=node_modules_go_brrr /app/public /var/www/html/public-npm
RUN rsync -ar /var/www/html/public-npm/ /var/www/html/public/ \
    && rm -rf /var/www/html/public-npm \
    && chown -R www-data:www-data /var/www/html/public

ENV AIKIDO_BLOCKING=true

# 5. Setup Entrypoint
EXPOSE 8080

ENTRYPOINT ["/entrypoint"]
