# syntax=docker/dockerfile:1.7
# Symfony 7.4 + API Platform served by FrankenPHP (Caddy). Same image runs the Messenger worker.
FROM dunglas/frankenphp:1-php8.3-bookworm AS base

RUN install-php-extensions pdo_pgsql intl zip opcache apcu redis \
 && apt-get update && apt-get install -y --no-install-recommends curl acl \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 APP_ENV=prod SERVER_NAME=":80"
WORKDIR /app

COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-zyntara.ini
COPY docker/Caddyfile /etc/caddy/Caddyfile

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress --no-interaction

COPY . .
RUN rm -f .env.local .env.test && composer dump-autoload --classmap-authoritative --no-dev \
 && composer dump-env prod \
 && php bin/console cache:warmup \
 && php bin/console assets:install public \
 && mkdir -p var/cache var/log /data/storage config/jwt \
 && chown -R www-data:www-data var /data/storage config/jwt

COPY docker/entrypoint.sh /usr/local/bin/zyntara-entrypoint
RUN chmod +x /usr/local/bin/zyntara-entrypoint

EXPOSE 80
HEALTHCHECK --interval=10s --timeout=3s --retries=10 CMD curl -fsS http://127.0.0.1/health || exit 1
ENTRYPOINT ["zyntara-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
