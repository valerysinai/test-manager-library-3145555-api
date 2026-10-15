# syntax=docker/dockerfile:1
FROM php:8.4-cli-alpine@sha256:37c8c64177612b72e7f9d2c00481739c166a3708bea27c1cfd27fea1291d82d1 AS php_base
RUN apk add --no-cache libpq oniguruma libxml2 unzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev oniguruma-dev libxml2-dev \
    && docker-php-ext-install -j2 pdo_pgsql mbstring dom \
    && apk del .build-deps
ADD --checksum=sha256:7a2d379d5b8ffdaa028580ef26494c36d2feef4b178d3dd1473a4dbc5e17c8d6 \
    --chmod=755 https://getcomposer.org/download/2.10.3/composer.phar /usr/local/bin/composer
WORKDIR /app
ENV TZ=UTC

FROM php_base AS runtime
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress
COPY . .
RUN composer dump-autoload --no-dev --no-scripts --optimize \
    && php artisan package:discover --ansi \
    && php -r 'if (!in_array("argon2id", password_algos(), true)) { exit(1); }'
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr
EXPOSE 8000
HEALTHCHECK --interval=15s --timeout=3s --start-period=10s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1:8000/api/v1/health") === "{\"status\":\"ok\"}" ? 0 : 1);'
CMD ["sh", "scripts/start-api.sh"]

FROM runtime AS test_dependencies
RUN composer install --no-scripts --no-interaction --prefer-dist --no-progress \
    && composer dump-autoload --no-scripts --optimize
FROM test_dependencies AS test
RUN php vendor/bin/phpunit

FROM test_dependencies AS integration
CMD ["php", "vendor/bin/phpunit", "--configuration", "phpunit.integration.xml"]

FROM runtime AS application
