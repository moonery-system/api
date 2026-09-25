FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    libpq-dev

# sockets: declarada por php-amqplib. Sem ela o composer barra qualquer require.
RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath xml sockets

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

RUN chown -R www-data:www-data /var/www

EXPOSE 9000

CMD ["php-fpm"]
