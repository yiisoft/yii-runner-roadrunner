ARG PHP_VERSION
FROM --platform=linux/amd64 php:${PHP_VERSION}-cli-alpine

# Install main package
RUN apk add git libzip-dev autoconf g++ make linux-headers

# Install PHP extensions
RUN docker-php-ext-install zip sockets

# Install PHP extensions from PECL
RUN pecl install xdebug pcov grpc protobuf \
    && docker-php-ext-enable xdebug pcov grpc protobuf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER 1

# Install RoadRunner
COPY --from=spiralscout/roadrunner:2.12.3 /usr/bin/rr /usr/local/bin/rr

# Set up work directory
ADD . /app
WORKDIR /app

RUN composer update --prefer-dist --no-interaction

ENTRYPOINT ["rr", "-d", "serve"]
