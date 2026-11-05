# PHP 8.3 para desarrollo (en WSL no hay PHP instalado). La misma versión que el hosting compartido.
FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd intl zip pdo_mysql bcmath exif \
    && rm -rf /var/lib/apt/lists/*

# Fotos de celular de varios MB (en el hosting lo mismo lo pone public/.user.ini).
RUN printf 'upload_max_filesize=20M\npost_max_size=64M\nmemory_limit=512M\nmax_file_uploads=20\n' > /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_HOME=/tmp/composer
WORKDIR /app
