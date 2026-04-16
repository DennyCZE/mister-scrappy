ARG PHP_VERSION="8.2-fpm-alpine"

FROM php:${PHP_VERSION}

ARG user
ARG uid
ARG laravel_env

#latest lts
ENV LD_LIBRARY_PATH="$LD_LIBRARY_PATH:/lib64:/usr/x86_64-linux-gnu/lib"

# Runtime libs (kept in the final image) + build-only deps (dropped after compile).
# Refs: https://stackoverflow.com/questions/44221775/docker-vfs-folder-size/73937539#73937539
RUN apk add --no-cache \
        curl \
        libzip \
        oniguruma \
        zip \
        unzip \
    && apk add --no-cache --virtual .build-deps \
        libxml2-dev \
        libzip-dev \
        oniguruma-dev \
    && docker-php-ext-install mbstring bcmath zip xml \
    && apk del .build-deps \
    # https://stackoverflow.com/a/72322396
    && ln -s /usr/x86_64-linux-gnu/lib64/ /lib64

# Create laravel user
RUN addgroup -g $uid -S $user && adduser -u $uid -S $user -G $user

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy php.ini configuration
COPY docker-compose/php.ini /usr/local/etc/php/php.ini

# Set working directory
WORKDIR /var/www

USER $user
