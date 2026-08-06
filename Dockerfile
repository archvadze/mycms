FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    libxml2-dev \
    curl-dev \
    oniguruma-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        intl \
        zip \
        curl \
        mbstring \
        dom \
        xml \
        xmlreader \
        opcache \
        bcmath \
        gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]
