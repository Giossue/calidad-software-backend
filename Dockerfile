# syntax=docker/dockerfile:1.7

FROM node:24-bookworm-slim AS node-runtime

FROM php:8.4-apache-bookworm AS php-extensions

RUN apt-get update \
    && apt-get install --no-install-recommends -y \
        curl \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && a2enmod expires headers rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

FROM php-extensions AS build

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=node-runtime /usr/local/bin/node /usr/local/bin/node
COPY --from=node-runtime /usr/local/lib/node_modules /usr/local/lib/node_modules

RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .

ARG VITE_APP_NAME="Calidad Software"
ENV VITE_APP_NAME=${VITE_APP_NAME}

RUN composer dump-autoload \
        --classmap-authoritative \
        --no-dev \
        --no-interaction \
    && npm run build \
    && rm -rf node_modules

FROM php:8.4-apache-bookworm AS production

RUN apt-get update \
    && apt-get install --no-install-recommends -y \
        curl \
        libicu72 \
        libpq5 \
        libzip4 \
    && a2enmod expires headers rewrite \
    && rm -rf /var/lib/apt/lists/*

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=warning

COPY docker/apache/ports.conf /etc/apache2/ports.conf
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY --from=php-extensions /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-extensions /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY docker/php/production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY docker/entrypoint.sh /usr/local/bin/application-entrypoint
COPY --from=build /var/www/html /var/www/html

RUN mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && ln -s ../storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/application-entrypoint

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl --fail --silent --show-error http://127.0.0.1:8080/up > /dev/null || exit 1

ENTRYPOINT ["application-entrypoint"]
CMD ["apache2-foreground"]
