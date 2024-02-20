FROM php:8-cli-alpine

# Add OpCache
RUN docker-php-ext-configure opcache --enable-opcache \
    && docker-php-ext-install opcache

# Install PDO MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Install Redis driver
RUN apk add --no-cache pcre-dev $PHPIZE_DEPS \
        && pecl install redis \
        && docker-php-ext-enable redis.so \
        && apk del --purge pcre-dev $PHPIZE_DEPS

# Install FFMPEG
RUN apk add --no-cache ffmpeg

# Install chromaprint / fpcalc (used by AcoustID for Audio Fingerprinting)
RUN apk add --no-cache chromaprint

# Use the default production configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Add our PHP configuration
COPY ./php.custom.conf "/usr/local/etc/php-fpm.d/zzz-shoutzor.conf"

# Switch to our working directory
WORKDIR /code

# Copy the default dotEnv file
COPY ./.env.default .env

# Copy PHP Project files
COPY ./ .

# Start the Queue Worker
CMD ["php", "artisan", "queue:work", "--queue=uploads,agent"]