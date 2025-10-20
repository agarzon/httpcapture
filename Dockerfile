FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    sqlite-libs \
    sqlite-dev \
    curl \
    bash \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/cache/apk/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

COPY . /var/www/html

RUN mkdir -p storage && chown -R www-data:www-data storage

USER www-data

EXPOSE 8080

CMD ["php", "-dopcache.enable=0", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
