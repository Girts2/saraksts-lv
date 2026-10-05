FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        libicu-dev \
        libzip-dev \
        procps \
        unzip \
    && docker-php-ext-install -j"$(nproc)" intl pdo_mysql zip \
    && docker-php-ext-enable opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/saraksts.ini
COPY docker/entrypoint.sh /usr/local/bin/saraksts-entrypoint
RUN chmod +x /usr/local/bin/saraksts-entrypoint

WORKDIR /var/www/html
EXPOSE 80

ENTRYPOINT ["saraksts-entrypoint"]
CMD ["apache2-foreground"]
