#!/bin/sh
# Reproduz o CI localmente, byte a byte: container PHP limpo (sem vendor, sem .env, sem cache do host) e um
# PostgreSQL 18 novo. Roda o MESMO scripts/ci.sh do workflow. Uso: scripts/reproduz-ci.sh  (na raiz do repo)
set -eu
cd "$(dirname "$0")/.."

NET=ci-net-$$
PG=ci-pg-$$
WORK=$(mktemp -d)
cleanup() { docker rm -f "$PG" >/dev/null 2>&1 || true; docker network rm "$NET" >/dev/null 2>&1 || true; docker run --rm -v "$WORK":/w alpine rm -rf /w/repo >/dev/null 2>&1 || true; rm -rf "$WORK"; }
trap cleanup EXIT

# Cópia limpa do que o Git versiona: o que não está no commit não pode ajudar o CI.
git ls-files -z --cached --others --exclude-standard | grep -zv '^vendor/' | xargs -0 -I{} sh -c 'mkdir -p "'"$WORK"'/repo/$(dirname "{}")" && cp -p "{}" "'"$WORK"'/repo/{}"'

docker network create "$NET" >/dev/null
docker run -d --name "$PG" --network "$NET" -e POSTGRES_USER=devfinder -e POSTGRES_PASSWORD=devfinder \
  -e POSTGRES_DB=devfinder_test postgres:18-alpine >/dev/null
until docker exec "$PG" pg_isready -U devfinder >/dev/null 2>&1; do sleep 1; done
# O workflow usa 1 banco só; o phpunit.xml aponta os testes para devfinder_test: criamos os dois, como o workflow.
docker exec "$PG" psql -U devfinder -d devfinder_test -c "CREATE DATABASE devfinder OWNER devfinder" >/dev/null

docker run --rm --network "$NET" -e DB_HOST="$PG" -e DB_PORT=5432 -v "$WORK/repo":/app -w /app devfinder-php scripts/ci.sh
