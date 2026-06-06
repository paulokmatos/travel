FROM php:8.3-cli

WORKDIR /var/www/html

ARG INSTALL_DEV=false

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl git unzip libzip-dev $PHPIZE_DEPS \
    && docker-php-ext-install bcmath opcache pcntl pdo_mysql zip \
    && pecl install redis pcov \
    && docker-php-ext-enable redis pcov \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN if [ "$INSTALL_DEV" = "true" ]; then \
        composer install --no-interaction --prefer-dist --no-scripts --optimize-autoloader; \
    else \
        composer install --no-interaction --prefer-dist --no-scripts --no-dev --optimize-autoloader; \
    fi

COPY . .
RUN chmod +x docker/entrypoint.sh \
    && touch .env \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --no-interaction --optimize

EXPOSE 8000

ENTRYPOINT ["docker/entrypoint.sh"]

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
