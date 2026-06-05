FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libpq-dev postgresql-client libzip-dev libicu-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql pgsql zip intl opcache pcntl sockets gd bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && sed -i 's/^listen = .*/listen = 0.0.0.0:9000/' /usr/local/etc/php-fpm.d/zz-docker.conf \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload -o --no-dev --no-scripts

RUN chown -R www-data:www-data storage bootstrap/cache

COPY deploy/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
COPY deploy/ensure-db-user.sh /usr/local/bin/ensure-db-user.sh
COPY deploy/bootstrap-app.sh /usr/local/bin/bootstrap-app.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh /usr/local/bin/ensure-db-user.sh /usr/local/bin/bootstrap-app.sh

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["php-fpm"]
