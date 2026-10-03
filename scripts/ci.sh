#!/bin/sh
# Gates do CI (PHP). O workflow e a reprodução local (scripts/reproduz-ci.sh) rodam ESTE script: o `.env` é
# gerado aqui, nunca copiado de um arquivo local (lição do v1: `.env` local com mais chaves escondeu falha do CI).
# Pré-requisitos: PHP 8.4 + composer e um PostgreSQL 18 acessível em DB_HOST:DB_PORT.
set -eu

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"

step() { printf '\n==== %s\n' "$1"; }

step "gera .env do CI (só as chaves que o workflow define)"
cat > .env <<ENV
APP_NAME=DevFinder
APP_ENV=testing
APP_DEBUG=false
APP_KEY=
LOG_CHANNEL=json
LOG_LEVEL=info
DB_CONNECTION=pgsql
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_DATABASE=devfinder
DB_USERNAME=devfinder
DB_PASSWORD=devfinder
CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
ENV

step "composer validate --strict"
composer validate --strict

step "composer install"
composer install --no-interaction --prefer-dist --no-progress

step "composer audit"
composer audit

step "chave da aplicação"
php artisan key:generate --force

step "pint --test"
vendor/bin/pint --test

step "larastan nível 9 (sem baseline)"
vendor/bin/phpstan analyse --memory-limit=1G --no-progress

step "config:check"
php artisan config:check

step "migrate e rollback e migrate (reversível)"
php artisan migrate --force
php artisan migrate:rollback --force
php artisan migrate --force

step "paridade de rotas: nada registrado fora do contrato"
php artisan gesso:routes --fail-on-undocumented \
  --exclude-route='scramble.*' --exclude-route='health*' --exclude-route='docs*' >/dev/null

step "pest (Feature, Contract, Arch)"
vendor/bin/pest

printf '\nCI PHP OK\n'
