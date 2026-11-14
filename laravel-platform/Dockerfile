FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu-dev \
    libzip-dev \
    gmp-dev \
    libsodium-dev \
    oniguruma-dev \
    libxml2-dev \
    openssl-dev \
    mysql-client \
    unzip \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        xml \
        bcmath \
        intl \
        gmp \
        sodium \
        pcntl \
        zip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json ./
RUN if [ -f composer.lock ]; then composer install --no-scripts --no-autoloader --prefer-dist; fi

COPY . .

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

EXPOSE 9000
CMD ["php-fpm"]
