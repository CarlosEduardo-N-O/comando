# syntax=docker/dockerfile:1
# Imagem única usada pelos serviços backend (PHP-FPM), worker e scheduler.

ARG PHP_IMAGE=php:8.4.15-fpm-alpine3.22
ARG COMPOSER_IMAGE=composer:2.8.12

FROM ${COMPOSER_IMAGE} AS composer

# ---------------------------------------------------------------------------
FROM ${PHP_IMAGE} AS base

RUN apk add --no-cache libpq libzip icu-libs fcgi \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev libzip-dev icu-dev linux-headers \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip pcntl bcmath opcache \
 && apk del .build-deps \
 && rm -rf /tmp/* /var/cache/apk/*

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-comando.ini
COPY docker/fpm.conf /usr/local/etc/php-fpm.d/zz-comando.conf
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1

# ---------------------------------------------------------------------------
# Dependências de produção instaladas durante o build a partir do composer.lock.
FROM base AS vendor
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist \
 && rm -rf /root/.composer/cache

# ---------------------------------------------------------------------------
FROM base AS production
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr
COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .
RUN rm -rf tests .env \
 && composer dump-autoload --no-dev --optimize --classmap-authoritative \
 && php artisan package:discover --ansi \
 && mkdir -p storage/app/private/anexos storage/framework/cache/data storage/framework/sessions \
             storage/framework/views storage/framework/testing storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R ug+rwX storage bootstrap/cache
# Processos sem root: PHP-FPM, worker e scheduler executam como www-data.
USER www-data
EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong || exit 1
CMD ["php-fpm", "-F"]

# ---------------------------------------------------------------------------
# Desenvolvimento e testes: inclui dependências de dev (PHPUnit etc.).
FROM base AS development
ENV APP_ENV=local
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist && rm -rf /root/.composer/cache
COPY . .
RUN rm -f .env \
 && composer dump-autoload --optimize \
 && php artisan package:discover --ansi \
 && mkdir -p storage/app/private/anexos storage/framework/cache/data storage/framework/sessions \
             storage/framework/views storage/framework/testing storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 9000
CMD ["php-fpm", "-F"]
