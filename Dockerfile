FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libpq-dev unzip \
    && docker-php-ext-install intl pdo_pgsql opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ENV APP_ENV=prod
ENV APP_DEBUG=0

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --optimize-autoloader

COPY . .
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

RUN rm -rf var/cache/* var/log/* \
    && composer dump-autoload --no-dev --classmap-authoritative \
    && mkdir -p var/cache var/log \
    && chown -R www-data:www-data var

EXPOSE 80

CMD ["apache2-foreground"]
