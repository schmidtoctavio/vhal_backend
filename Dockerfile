FROM php:8.4-fpm-alpine

WORKDIR /var/www/html

RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    linux-headers \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        intl \
        zip \
        bcmath \
        pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

CMD ["php-fpm"]