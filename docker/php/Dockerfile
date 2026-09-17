FROM php:8.4-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libicu-dev \
        librabbitmq-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        intl \
        opcache \
    && rm -rf /var/lib/apt/lists/* \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && pecl install amqp \
    && docker-php-ext-enable amqp

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
