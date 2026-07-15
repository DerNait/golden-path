FROM php:8.3-fpm

ARG WWWGROUP=1000
ARG WWWUSER=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl unzip libcurl4-openssl-dev libonig-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql pdo_sqlite bcmath curl mbstring gd zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN groupadd --force -g ${WWWGROUP} golden && useradd -ms /bin/bash --no-user-group -g ${WWWGROUP} -u ${WWWUSER} golden

WORKDIR /var/www
COPY composer.json composer.lock /var/www/
RUN composer install --no-interaction --prefer-dist --no-scripts
COPY --chown=golden:golden . /var/www
RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/{cache,sessions,views} bootstrap/cache \
    && chown -R golden:golden storage bootstrap/cache

USER golden
EXPOSE 9000
CMD ["php-fpm"]
