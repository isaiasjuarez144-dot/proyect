FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libxml2-dev libzip-dev unzip \
    && docker-php-ext-install mbstring pdo_mysql zip \
    && pecl install xdebug-3.5.3 \
    && docker-php-ext-enable xdebug \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
ENV XDEBUG_MODE=off

COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist

COPY . .

EXPOSE 80
