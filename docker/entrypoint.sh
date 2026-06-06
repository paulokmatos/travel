#!/bin/sh
set -eu

if [ "${DB_CONNECTION:-}" = "mysql" ]; then
    echo "Waiting for MySQL at ${DB_HOST:-mysql}:${DB_PORT:-3306}..."

    until php -r '
        $host = getenv("DB_HOST") ?: "mysql";
        $port = getenv("DB_PORT") ?: "3306";
        $database = getenv("DB_DATABASE") ?: "travel";
        $username = getenv("DB_USERNAME") ?: "root";
        $password = getenv("DB_PASSWORD") ?: "";

        try {
            new PDO("mysql:host={$host};port={$port};dbname={$database}", $username, $password);
        } catch (Throwable $exception) {
            exit(1);
        }
    '; do
        sleep 1
    done
fi

if [ "${APP_RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

if [ "${APP_RUN_SEEDERS:-false}" = "true" ]; then
    php artisan db:seed --force
fi

exec "$@"
