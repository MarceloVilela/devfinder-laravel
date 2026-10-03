# php-laravel

API do DevFinder em **Laravel + PostgreSQL (Neon) em AWS Lambda via Bref**, com o mesmo contrato público de
`devfinder-api` e custo zero. Reescrita do `php-codei` (CodeIgniter 4 + MySQL).

**Estado**: Fase 3 (leitura pública) implementada localmente. Há esqueleto Laravel com CI, migrations, as 10 rotas públicas de leitura
(`/devs`, `/channels`, `/feed/*`, `/video/{id}`, `/description/*`, `/search`, sem personalização por token) e um deploy real (Lambda +
Function URL + Neon) da Fase 2b. O aceite da Fase 3 no deploy real ainda não foi feito; o deploy automático por OIDC ainda não rodou.

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

- Latência medida no deploy real (`specs/spikes/g1-latencia-deploy-real.md`): quente com banco p95 51 ms; Lambda e Neon frios ≈ 2,2 s; **Lambda frio sem banco 1,64 s, acima da meta de 1,5 s**. O custo medido é o do Cost Explorer, que atrasa horas.
- **Sem defesa de infraestrutura contra abuso volumétrico**: a conta AWS tem limite de concorrência 10 e não permite concorrência reservada, e a Function URL não tem WAF nem throttling. Sobram o rate limiting da aplicação (Fase 4) e o alarme de custo.
- O oráculo de paridade (v1) cobre 27 das 30 operações do contrato.
- O Free Plan da AWS encerra a conta ao expirar; a decisão de fim de vida ainda não foi tomada.
