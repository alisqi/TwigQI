FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*
RUN pecl install xdebug-3.3.2
RUN docker-php-ext-enable xdebug

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Keep the container running
CMD ["tail", "-f", "/dev/null"]
