# syntax=docker/dockerfile:1.7

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

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist

COPY . .

RUN composer dump-autoload \
        --classmap-authoritative \
        --no-dev \
        --no-interaction

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
