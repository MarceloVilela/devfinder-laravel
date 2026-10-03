#!/bin/sh
# Roda um comando no container da aplicação (o host não tem php nem composer).
# Uso: ./run.sh composer install | ./run.sh php artisan about | ./run.sh vendor/bin/pest
exec docker compose run --rm --no-deps -u "$(id -u):$(id -g)" -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer \
  -e DB_HOST=db -e DB_PORT=5432 app "$@"
