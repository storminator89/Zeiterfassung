FROM php:8.5.12-apache-trixie@sha256:7cd25eb48f9763dfeabe8fda3939897aca2001802fadeafa1b602f26c735ef96
# PDO SQLite and mbstring are included in the official image.
WORKDIR /var/www/html
COPY . /var/www/html
RUN find /var/www/html -type d -exec chmod 755 {} + \
    && find /var/www/html -type f -exec chmod 644 {} + \
    && mkdir -p /var/lib/chrono && chown www-data:www-data /var/lib/chrono \
    && chmod 700 /var/lib/chrono \
    && printf '<Directory /var/www/html>\nAllowOverride All\nOptions -Indexes\n</Directory>\n' > /etc/apache2/conf-available/chrono.conf \
    && a2enconf chrono
ENV APP_DATA_DIR=/var/lib/chrono
VOLUME /var/lib/chrono
EXPOSE 80
