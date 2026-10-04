#!/bin/sh
# Simula PRODUÇÃO localmente, sem AWS: cópia limpa + `composer install --no-dev`, APP_ENV=production, APP_DEBUG=false,
# DB_URL no formato do Neon (uma URL só), CORS por variável, migrations e o smoke test completo.
# Prova o que dá para provar antes do deploy (config:check de produção, ausência de classes dev-only, CORS, erros).
# Não prova o Bref/Lambda nem o Neon. Precisa de `docker compose up -d db` e da imagem `devfinder-php`.
set -eu
cd "$(dirname "$0")/.."
WORK=$(mktemp -d); NAME=prodsim-$$; DBN=prodsim_$$
cleanup() {
  docker rm -f "$NAME" >/dev/null 2>&1 || true
  docker compose exec -T db psql -U devfinder -c "drop database if exists $DBN" >/dev/null 2>&1 || true
  docker run --rm -v "$WORK":/w alpine rm -rf /w/repo >/dev/null 2>&1 || true; rm -rf "$WORK"
}
trap cleanup EXIT

git ls-files -z --cached --others --exclude-standard | grep -zv '^vendor/' | xargs -0 -I{} sh -c 'mkdir -p "'"$WORK"'/repo/$(dirname "{}")" && cp -p "{}" "'"$WORK"'/repo/{}"'
docker run --rm -u "$(id -u):$(id -g)" -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer -v "$WORK/repo":/app -w /app devfinder-php \
  composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress >/dev/null
docker compose exec -T db psql -U devfinder -c "create database $DBN owner devfinder" >/dev/null

KEY="base64:$(head -c32 /dev/urandom | base64)"
ENVS="-e APP_ENV=production -e APP_DEBUG=false -e APP_KEY=$KEY -e DB_CONNECTION=pgsql -e DB_URL=postgresql://devfinder:devfinder@db:5432/$DBN
 -e CORS_ALLOWED_ORIGINS=https://app.example.test -e APP_WEB_URL=https://app.example.test
 -e JWT_SECRET=$(head -c32 /dev/urandom | base64 | tr -d '/+=') -e GITHUB_CLIENT_ID=smoke-client -e GITHUB_CLIENT_SECRET=smoke-secret -e LOG_CHANNEL=json -e CACHE_STORE=array -e SESSION_DRIVER=array"
NET=php-laravel_default
# shellcheck disable=SC2086
docker run --rm --network "$NET" -v "$WORK/repo":/app -w /app $ENVS devfinder-php sh -c 'php artisan config:check && php artisan migrate --force | tail -3'
# shellcheck disable=SC2086
docker run -d --name "$NAME" --network "$NET" -p 8083:8000 -v "$WORK/repo":/app -w /app $ENVS devfinder-php php -S 0.0.0.0:8000 -t public public/index.php >/dev/null
i=0; until curl -fs localhost:8083/health >/dev/null 2>&1; do i=$((i+1)); [ "$i" -gt 30 ] && { echo "app não subiu"; docker logs "$NAME"; exit 1; }; sleep 1; done
BASE_URL=http://localhost:8083 ORIGIN=https://app.example.test scripts/smoke.sh
