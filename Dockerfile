ARG PHP_VERSION="8.2-fpm"

FROM php:${PHP_VERSION}

ARG user
ARG uid
ARG laravel_env

#latest lts
ARG NODE_VERSION="22.13.1"
ENV LD_LIBRARY_PATH="$LD_LIBRARY_PATH:/lib64:/usr/x86_64-linux-gnu/lib"

# Install system dependencies one-line https://stackoverflow.com/questions/44221775/docker-vfs-folder-size/73937539#73937539
RUN apt-get update \
    && apt-get install -y \
		git \
		curl \
        libpng-dev \
		libonig-dev \
		libxml2-dev \
		libzip-dev \
        libsqlite3-dev \
		zip \
		unzip \
		sqlite3 \
    # Clear cache
		&& apt-get clean \
		&& rm -rf /var/lib/apt/lists/* \
	# Install PHP extensions
		&& docker-php-ext-install pdo_sqlite mbstring exif pcntl bcmath gd zip \
    # https://stackoverflow.com/a/72322396
    	&& ln -s /usr/x86_64-linux-gnu/lib64/ /lib64 \
    # Install Node.JS
    	&& curl https://nodejs.org/dist/v$NODE_VERSION/node-v$NODE_VERSION-linux-x64.tar.gz | tar -xz -C /usr/local --strip-components 1 \
	# Create system user to run Composer and Artisan Commands
		&& useradd -G www-data,root -u $uid -d /home/$user $user \
		&& mkdir -p /home/$user/.composer \
		&& chown -R $user:$user /home/$user

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy php.ini configuration
COPY docker-compose/php.ini /usr/local/etc/php/php.ini

# Set working directory
WORKDIR /var/www

USER $user
