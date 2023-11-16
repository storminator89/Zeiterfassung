# Basis-Image: PHP 8.2 mit Apache
FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite

# Installieren der erforderlichen PHP-Erweiterungen
RUN docker-php-ext-install pdo pdo_sqlite

# Arbeitsverzeichnis im Container setzen
WORKDIR /var/www/html

# Kopieren der Anwendungsdateien in das Arbeitsverzeichnis
COPY . /var/www/html

RUN chown www-data:www-data /var/www/html/timetracking.sqlite

# Öffnen Sie Port 80 für den Zugriff auf Ihren Container
EXPOSE 80
