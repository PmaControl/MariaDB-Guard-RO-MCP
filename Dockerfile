FROM php:8.2-apache

LABEL org.opencontainers.image.source="https://github.com/PmaControl/AsterDB-MCP"
LABEL org.opencontainers.image.description="MCP MariaDB/MySQL server with guarded query policies"
LABEL org.opencontainers.image.licenses="GPL-3.0-or-later"

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates \
    && docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite headers setenvif \
    && rm -rf /var/lib/apt/lists/*

RUN sed -i 's/Listen 80/Listen 13306/' /etc/apache2/ports.conf
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY public /var/www/html/public
COPY src /var/www/html/src

EXPOSE 13306

CMD ["apache2-foreground"]
