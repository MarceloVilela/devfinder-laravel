# php-laravel

API do DevFinder em **Laravel + PostgreSQL (Neon) em AWS Lambda via Bref**, com o mesmo contrato público de
`devfinder-api` e custo zero. Reescrita do `php-codei` (CodeIgniter 4 + MySQL).

**Estado**: Fase 2a (esqueleto local) em andamento. Há esqueleto Laravel com `/health`, `/health/db`, `/docs` e `GET /v1`, CI e
migrations; **nenhuma regra de domínio, deploy nem demo ainda**.

- Plano: [`plan.md`](./plan.md)
- Estado das specs: [`specs/README.md`](./specs/README.md)
- Contrato: [`specs/fase-0-openapi.yaml`](./specs/fase-0-openapi.yaml)

## Rodar localmente

O host não precisa de PHP nem Composer, só Docker:

```sh
cp .env.example .env
docker compose up -d --build      # PostgreSQL 18 + app em http://localhost:8082
./run.sh php artisan migrate:fresh --seed --seeder='Database\Seeders\ParityDatasetSeeder'
./run.sh vendor/bin/pest          # testes (usam o banco devfinder_test)
scripts/reproduz-ci.sh            # o CI inteiro, em container limpo
```

## Limitações conhecidas (até agora)

- Nenhuma medição de latência, cold start ou custo foi feita.
- O oráculo de paridade (v1) cobre 27 das 30 operações do contrato.
- O Free Plan da AWS encerra a conta ao expirar; a decisão de fim de vida ainda não foi tomada.
