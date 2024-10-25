FROM php:8.1-cli

RUN apt update
RUN apt install unzip
RUN pecl install xdebug-3.3.2
RUN docker-php-ext-enable xdebug

COPY --from=composer:2.1 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Keep the container running
CMD ["tail", "-f", "/dev/null"]
