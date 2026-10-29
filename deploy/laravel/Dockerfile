# syntax=docker/dockerfile:1
# Explicit operator/candidate image; the root Dockerfile is the Laravel web default.
FROM php:8.5.12-apache-trixie@sha256:7cd25eb48f9763dfeabe8fda3939897aca2001802fadeafa1b602f26c735ef96 AS dependencies
WORKDIR /opt/chrono/framework
ENV APP_ENV=production APP_DEBUG=false \
    FRAMEWORK_FRESH_WEB_ENABLED=false \
    FRAMEWORK_CHRONO_READ_ENABLED=false FRAMEWORK_CHRONO_WRITE_ENABLED=false
# Official Composer release, hash from https://getcomposer.org/download/.
ADD --checksum=sha256:7a2d379d5b8ffdaa028580ef26494c36d2feef4b178d3dd1473a4dbc5e17c8d6 https://getcomposer.org/download/2.10.3/composer.phar /usr/local/bin/composer.phar
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*
COPY framework/ /opt/chrono/framework/
COPY lib/ /opt/chrono/lib/
COPY languages/ /opt/chrono/languages/
COPY docs/framework/schema-map.json /opt/chrono/docs/framework/schema-map.json
COPY LICENSE /opt/chrono/LICENSE
# Only maintained Composer autoload/package discovery hooks. No .env, keys,
# migration, seed, database or configuration/route-cache creation.
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && COMPOSER_ALLOW_SUPERUSER=1 php /usr/local/bin/composer.phar validate --strict \
    && COMPOSER_ALLOW_SUPERUSER=1 php /usr/local/bin/composer.phar install --no-dev --prefer-dist --no-interaction --no-progress --no-plugins \
    && php /usr/local/bin/composer.phar check-platform-reqs --no-dev

FROM php:8.5.12-apache-trixie@sha256:7cd25eb48f9763dfeabe8fda3939897aca2001802fadeafa1b602f26c735ef96
WORKDIR /opt/chrono/framework
ENV APP_ENV=production APP_DEBUG=false \
    FRAMEWORK_FRESH_WEB_ENABLED=false \
    FRAMEWORK_CHRONO_READ_ENABLED=false FRAMEWORK_CHRONO_WRITE_ENABLED=false
COPY --from=dependencies /opt/chrono/ /opt/chrono/
COPY deploy/laravel/apache.conf /etc/apache2/sites-available/chrono-laravel.conf
COPY deploy/laravel/php.ini /usr/local/etc/php/conf.d/chrono-laravel.ini
COPY --chmod=0555 deploy/laravel/web-entrypoint.sh /usr/local/bin/chrono-laravel-web
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini \
    && a2dissite 000-default \
    && a2enmod ssl rewrite headers reqtimeout \
    && a2enconf docker-php \
    && a2ensite chrono-laravel \
    && printf 'Listen 8443 https\n' > /etc/apache2/ports.conf \
    && printf 'ServerName localhost\nServerTokens Prod\nServerSignature Off\nTraceEnable Off\n' > /etc/apache2/conf-available/chrono-laravel.conf \
    && a2enconf chrono-laravel \
    && find /opt/chrono -type d -exec chmod 0755 {} + \
    && find /opt/chrono -type f -exec chmod 0644 {} +
# Numeric runtime identity is overridden by Compose to match the existing
# private host directory. There is no root entrypoint or chmod/chown repair.
USER 65532:65532
ENTRYPOINT ["docker-php-entrypoint"]
# Running this image alone never starts a listener or provisions an instance.
CMD ["php", "artisan", "list", "--raw"]
