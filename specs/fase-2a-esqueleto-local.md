# Fase 2a — Esqueleto local e CI

> Referência: [`../plan.md`](../plan.md), seção 7, Fase 2a. Branch `fase-2a-esqueleto-local`, a partir de `main` (PR #3 mergeado).
> Status: **implementada, aguardando revisão** (iniciada e implementada em 2026-10-03). Spec escrita antes do código; o aceite se prova em `execucao-fase-2a.log`.

## Escopo

Caminho completo e **vazio** que roda no host (via Docker) e no CI. Nenhuma regra de domínio, nenhum recurso de nuvem
(isso é a 2b). A única feature é `Health`.

| Entregável | Critério verificável |
|---|---|
| Laravel 13 na raiz, PHP 8.4 (ADR 0009) | `php artisan about` em container; `database/migrations/` da Fase 1 intacto |
| `docker compose` (app + PostgreSQL 18) | `docker compose up -d` e `GET /health` 200 no host |
| `GET /health` (sem banco) e `GET /health/db` | testes de feature; `/health/db` faz `select 1` e dá 503 no formato do contrato se o banco cair |
| Pint (PER + `strict_types`), Larastan nível 9 sem baseline, Pest | `vendor/bin/pint --test`, `phpstan`, `pest` com `echo $?` = 0 |
| `composer validate --strict` e `composer audit` | código de saída 0 |
| `preventLazyLoading` fora de produção | teste que falha ao carregar relação preguiçosa |
| Handler global de erros (ADR 0008) | 404, 405, 422, 500 e 503 nos formatos de `erros-v1.md`; sem stack trace com `APP_DEBUG=false` |
| `/docs` com o contrato (ADR 0007) | `GET /docs` 200 e `GET /docs/openapi.yaml` serve `specs/fase-0-openapi.yaml` |
| Teste de contrato de exemplo | `GET /` validado contra o YAML pela ferramenta escolhida |
| Teste de arquitetura | regras da `arquitetura-alvo.md`; um PR/commit de prova em que ele falha de propósito |
| `config:check` + log de boot | comando artisan que falha se faltar chave obrigatória; log lista **nomes**, nunca valores |
| Logs JSON com id de requisição | cabeçalho `X-Request-Id` na resposta e o mesmo id em cada linha de log |
| Seeder do dataset de paridade | 35 devs, 3 canais, 3 tags, 55 vídeos, 2 + 2 reações (`dataset-de-paridade.md`) |
| Normalizador do G3 | `specs/tools/normalize-g3.*` conforme a regra 2 do dataset, com teste |
| `ci.yml` | todos os gates acima, em PR; `.env` gerado pelo próprio workflow |

## Escolhas a decidir com exemplo real (evidência em `specs/spikes/`)

| Escolha | Candidatas | Como se decide | ADR |
|---|---|---|---|
| Ferramenta de contrato | Spectator, `laravel-openapi-validator`, Gesso | cada uma valida `GET /` e um caso de erro contra o YAML **e** reprova uma resposta propositalmente errada; vale a que passa nos dois, com YAML 3.x sem edição e sem acoplar a Scramble | 0007 |
| DTO | classe `readonly` própria, `spatie/laravel-data` | um `StoreVideoData` de exemplo; a decisão pesa dependência, tamanho do pacote Lambda e validação duplicada | 0005 |
| Teste de arquitetura | Pest `arch()`, PHPat | as regras da seção "Estrutura" de `arquitetura-alvo.md`; vale quem expressa "controller não toca Eloquent exceto `Queries/`" | 0005 |
| Scramble | usar só como detector de divergência, ou descartar | roda sem erro e a diferença para o YAML é legível; senão descarta | 0007 |

Ao fim, as ADRs 0005 a 0008 passam a `aceita` **com o resultado medido**. Se uma escolha não passar, a ADR registra a
alternativa e o motivo, e eu paro e pergunto antes de seguir.

## Fora do escopo da 2a

Deploy, OIDC, Budgets, Function URL, SSM (2b); qualquer rota das 30 do contrato além de `GET /` (se o contrato tiver
`GET /` como teste de exemplo, a rota existe só como estágio do teste de contrato e é a mesma da Fase 3).

## Riscos

- O host não tem `php` nem `composer`: todo comando roda em container; a imagem precisa de `pdo_pgsql`, `intl`, `zip`, `bcmath`.
- Larastan nível 9 pode exigir anotações em código gerado do Laravel; exceção pontual com justificativa, sem baseline global.
- Pacotes de contrato podem não aceitar OpenAPI 3.1 ou Laravel 13: se ocorrer, é achado, não gambiarra.

## Critério de aceite

- [x] CI verde, reproduzido byte a byte (container limpo, `.env` do workflow), saída em `execucao-fase-2a.log` (**o workflow do GitHub só roda no PR**; falta vê-lo verde lá).
- [x] `docker compose up` local e `GET /health` e `/health/db` respondendo (log, seção 4).
- [x] Teste de arquitetura reprovado de propósito (prova) e depois verde (`specs/tools/prova-arch.sh`, log, seção 2).
- [x] Ferramenta de contrato escolhida com evidência (Gesso, `spikes/2a-ferramentas.md`). [x] ADRs 0005 a 0008 aceitas pelo usuário em 2026-10-03.
- [x] Seeder gera 35 / 3 / 3 / 55 / 2 / 2 e o normalizador tem teste (`ParityDatasetSeederTest`, `normalize-g3.test.cjs`).
- [ ] PR mergeado (ação do usuário).
