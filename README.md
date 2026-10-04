# DevFinder API — Laravel

<div flex-direction="row">
  <img src="https://img.shields.io/static/v1?style=for-the-badge&logo=PHP&label=php&message=8.4&color=success" />
  <img src="https://img.shields.io/static/v1?style=for-the-badge&logo=Laravel&label=laravel&message=13&color=success" />
  <img src="https://img.shields.io/static/v1?style=for-the-badge&logo=PostgreSQL&label=postgresql&message=18&color=success" />
  <img src="https://img.shields.io/static/v1?style=for-the-badge&logo=AmazonAWS&label=aws%20lambda&message=bref&color=success" />
</div>

## Sobre o projeto

**DevFinder** cataloga canais e vídeos brasileiros de tecnologia no YouTube. Devs entram com o GitHub, curtem ou descurtem outros devs e seguem ou
ignoram canais para personalizar o feed de vídeos. Há busca unificada, textos prontos para descrição de vídeo e uma ingestão agendada que mantém o
catálogo atualizado.

Esta é a versão em **PHP/Laravel da API**: mesmo domínio e mesmo contrato público do
[`devfinder-api`](https://github.com/MarceloVilela/devfinder-api) original (Express + MongoDB), consumido pelo
[`devfinder-next`](https://github.com/MarceloVilela/devfinder-next). Os projetos irmãos são o
[`devfinder-serverless`](https://github.com/MarceloVilela/devfinder-serverless) (Node, AWS Lambda e DynamoDB) e o
[`devfinder-codeigniter`](https://github.com/MarceloVilela/devfinder-codeigniter) (CodeIgniter 4 e MySQL), que serve de oráculo de paridade.

Feita de forma **spec-driven**: spec antes do código, um PR por fase, decisões em ADRs e a evidência de cada fase em log. O plano está em
[`plan.md`](./plan.md), o estado das specs em [`specs/README.md`](./specs/README.md) e o contrato em
[`specs/fase-0-openapi.yaml`](./specs/fase-0-openapi.yaml) (Swagger UI em `/docs`).

## Stack

| | |
|---|---|
| Linguagem e framework | PHP 8.4, Laravel 13 |
| Banco | PostgreSQL 18 ([Neon](https://neon.tech)) |
| Execução | AWS Lambda com [Bref](https://bref.sh), Function URL; ingestão agendada por regra do EventBridge |
| Autenticação | GitHub OAuth, JWT em cookie `httpOnly` ou `Authorization: Bearer`, papéis `USER` e `ADMIN` |
| Deploy | GitHub Actions com OIDC; segredos no SSM Parameter Store |
| Qualidade | Pest (feature, contrato, arquitetura), Larastan nível 9, Pint |

## Como rodar

Só precisa de Docker (o host não precisa de PHP nem de Composer):

```sh
cp .env.example .env              # preencher JWT_SECRET (openssl rand -hex 32) e, para o login, o OAuth App do GitHub
docker compose up -d --build      # PostgreSQL 18 + app em http://localhost:8082 (Swagger UI em /docs)
./run.sh php artisan migrate:fresh --seed --seeder='Database\Seeders\ParityDatasetSeeder'
./run.sh vendor/bin/pest          # testes (rodam só no banco devfinder_test)
scripts/reproduz-ci.sh            # o CI inteiro, em container limpo
```

Útil no dia a dia:

```sh
./run.sh php artisan auth:mint-token dev01                                          # token sintético (só fora de produção)
./run.sh php artisan video:refresh --fixture=tests/Fixtures/jsonbin-videos.json     # ingestão sem rede
docker compose exec -T db psql -U devfinder -d devfinder \
  -c "update devs set role = 'ADMIN' where username = 'dev01'"                      # ADMIN se define direto no banco
```

Casos de aceite em [`specs/acceptance/`](./specs/acceptance/README.md) (`npx httpyac send *.http --env local --all`). O frontend aponta para a API com
`NEXT_PUBLIC_API_URL=http://localhost:8082/v1`.
