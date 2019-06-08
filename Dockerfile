FROM php:7.1-fpm
MAINTAINER Tseho <quentin.favrie@gmail.com>

RUN apt-get update

# Common extensions
RUN apt-get install -y \
        libicu-dev \
        zlib1g-dev \
        zip \
        unzip \
    && docker-php-ext-install \
        bcmath \
        intl \
        mbstring \
        zip

# Caching extensions
RUN pecl install \
        apcu \
    && docker-php-ext-enable \
        apcu \
        opcache

# Cleanup
RUN apt-get purge -y \
        libicu-dev \
        libpng-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

ARG DEBUG="false"
RUN if test "$DEBUG" = "true"; then \
    pecl install \
        xdebug \
    && docker-php-ext-enable \
        xdebug \
    ; fi

COPY ./php.ini /usr/local/etc/php/conf.d/custom.ini
COPY ./php-fpm.conf /usr/local/etc/php-fpm.d/docker.conf

COPY ./ /var/www/

WORKDIR /var/www/

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER 1

RUN composer install --prefer-dist --no-dev --no-scripts --no-progress --no-suggest --classmap-authoritative --no-interaction \
    && composer clear-cache
