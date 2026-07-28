# syntax=docker/dockerfile:1

FROM dunglas/frankenphp:1.12-php8.5 AS base

RUN install-php-extensions pdo_pgsql intl opcache zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

WORKDIR /app

FROM base AS dev

ENV APP_ENV=dev
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

FROM base AS prod

ENV APP_ENV=prod
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php-prod.ini "$PHP_INI_DIR/conf.d/zz-app.ini"

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
 && php bin/console cache:warmup
RUN chmod +x docker/entrypoint.sh

ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/app/docker/Caddyfile"]
