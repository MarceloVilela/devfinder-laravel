#!/bin/sh
# Reproduz o job `package` do CI localmente: cópia limpa do que o Git versiona, `composer install --no-dev` em container
# (o host não tem php), `osls package` e a allowlist no host (precisa de node). Sem credenciais da AWS.
set -eu
cd "$(dirname "$0")/.."
WORK=$(mktemp -d)
cleanup() { docker run --rm -v "$WORK":/w alpine rm -rf /w/repo >/dev/null 2>&1 || true; rm -rf "$WORK"; }
trap cleanup EXIT

git ls-files -z --cached --others --exclude-standard | grep -zv '^vendor/' | xargs -0 -I{} sh -c 'mkdir -p "'"$WORK"'/repo/$(dirname "{}")" && cp -p "{}" "'"$WORK"'/repo/{}"'

docker run --rm -u "$(id -u):$(id -g)" -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer -v "$WORK/repo":/app -w /app devfinder-php \
  composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress
cd "$WORK/repo"
rm -rf .serverless
AWS_SHARED_CREDENTIALS_FILE=/dev/null AWS_CONFIG_FILE=/dev/null AWS_ACCESS_KEY_ID= AWS_SECRET_ACCESS_KEY= \
  npx --yes osls@4.4.0 package --stage prod --param="corsOrigins=https://app.example.test" --param="webUrl=https://app.example.test"
ls -la .serverless/*.zip
echo "tamanho descompactado: $(unzip -l .serverless/*.zip | tail -1)"
node scripts/allowlist.cjs .serverless
echo "recursos do template:"
node -e "const t=require('./.serverless/cloudformation-template-update-stack.json');for(const [k,v] of Object.entries(t.Resources))console.log('  ',v.Type,k)"
