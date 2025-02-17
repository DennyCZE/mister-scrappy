ARG PHP_VERSION="8.2-fpm-alpine"

FROM php:${PHP_VERSION}

ARG user
ARG uid
ARG laravel_env

#latest lts
ENV LD_LIBRARY_PATH="$LD_LIBRARY_PATH:/lib64:/usr/x86_64-linux-gnu/lib"

# Install system dependencies one-line https://stackoverflow.com/questions/44221775/docker-vfs-folder-size/73937539#73937539
RUN apk update && apk add --no-cache \
		curl \
		libxml2-dev \
		libzip-dev \
		zip \
		unzip \
        oniguruma-dev \
	# Install PHP extensions
		&& docker-php-ext-install mbstring bcmath zip xml \
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
