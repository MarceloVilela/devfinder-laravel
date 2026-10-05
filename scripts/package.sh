#!/bin/sh
# Empacota para o Lambda SEM publicar nada: `composer install --no-dev` + `osls package`. Gera `.serverless/`.
# Não cria recurso na AWS (osls package só escreve arquivos). Uso (na raiz, num diretório limpo): scripts/package.sh
set -eu

composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress
# NÃO rodar `config:cache` aqui: o bref/laravel-bridge cacheia a config no cold start (em /tmp), com as variáveis reais.
# Cachear no build congelaria no pacote os valores do CI.
rm -rf .serverless
npx --yes osls@4.4.0 package --stage "${STAGE:-prod}" --param="corsOrigins=https://app.example.test" --param="webUrl=https://app.example.test"
ls -la .serverless/*.zip .serverless/cloudformation-template-update-stack.json
node scripts/allowlist.cjs .serverless

# Fase 7: o php.ini do projeto (expose_php=0, zend.exception_ignore_args=1) precisa estar DENTRO do pacote, senão o Bref não o lê.
unzip -p .serverless/*.zip php/conf.d/devfinder.ini > /dev/null || { echo "FALHA: php/conf.d/devfinder.ini fora do pacote"; exit 1; }
echo "php/conf.d/devfinder.ini presente no pacote"
