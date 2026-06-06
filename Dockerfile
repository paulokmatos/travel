FROM php:8.3-cli

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl git unzip libzip-dev \
    && docker-php-ext-install bcmath opcache pcntl pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-dev --optimize-autoloader

COPY . .
RUN chmod +x docker/entrypoint.sh \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --no-interaction --optimize

EXPOSE 8000

ENTRYPOINT ["docker/entrypoint.sh"]

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
