FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libpq-dev unzip \
    && docker-php-ext-install intl pdo_pgsql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ENV APP_ENV=prod
ENV APP_DEBUG=0
ARG INSTALL_DEV_DEPENDENCIES=0

COPY composer.json composer.lock ./
RUN if [ "$INSTALL_DEV_DEPENDENCIES" = "1" ]; then \
        composer install --prefer-dist --no-interaction --no-progress --no-scripts; \
    else \
        composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --optimize-autoloader; \
    fi

COPY . .
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

RUN rm -rf var/cache/* var/log/* \
    && if [ "$INSTALL_DEV_DEPENDENCIES" = "1" ]; then \
        composer dump-autoload; \
    else \
        composer dump-autoload --no-dev --classmap-authoritative && rm -rf tests; \
    fi \
    && APP_SECRET=asset-build-placeholder MAILER_DSN=smtp://mailer:1025 MAILER_SENDER=no-reply@localhost php bin/console asset-map:compile \
    && mkdir -p var/cache var/log \
    && chown -R www-data:www-data var

EXPOSE 80

CMD ["apache2-foreground"]
