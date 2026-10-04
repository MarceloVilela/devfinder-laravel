# Fase 2b — Esqueleto na nuvem (Bref + Neon), deploy e guardrails de custo

> Referência: [`../plan.md`](../plan.md), seção 7, Fase 2b. Branch `fase-2b-esqueleto-nuvem`, a partir de `main` (PR #4 mergeado).
> Status: **parte do repositório implementada; parte da conta (AWS e Neon) aguardando autorização** (iniciada em 2026-10-03). Spec antes do código; aceite se prova em `execucao-fase-2b.log`.

## Divisão do trabalho: o que é repositório e o que mexe na conta

| Parte | Mexe em conta AWS/Neon? | Quem decide |
|---|---|---|
| `serverless.yml`, Bref, `config/cors.php`, `config/database.php` de produção, scripts, `deploy.yml`, `teardown.md`, allowlist | não (só arquivos; `osls package` não cria nada) | eu, autônomo |
| Provedor OIDC e role de deploy (IAM) | **sim** | **você autoriza** antes de eu criar |
| AWS Budgets (alerta em US$ 1) | **sim** | **você autoriza** |
| Parâmetros do SSM (URL do banco, `APP_KEY`) | **sim** (grava segredo) | **você autoriza** |
| Primeiro `osls deploy` (cria função, Function URL, bucket, log group) | **sim** (gasta crédito, ainda que perto de zero) | **você autoriza** |
| `migrate` no Neon (extensões `unaccent`, `pg_trgm`, `uuidv7()`) | **sim** (altera o banco) | **você autoriza** |
| Rotação da senha do `neondb_owner` | **sim** | **você autoriza** |

## Entregáveis e critérios verificáveis

| Entregável | Critério |
|---|---|
| `serverless.yml` (função `web` em `php-84-fpm`, Function URL, região `us-east-2`) | `osls package` gera o template sem erro |
| Allowlist de recursos | `scripts/allowlist.sh` lê o template empacotado e **falha** se houver tipo fora da lista; teste com um template adulterado |
| Segredos fora do template | nenhum valor sensível em `serverless.yml`; `bref-ssm:` resolve em tempo de execução; teste que procura segredo no template |
| Logs com retenção curta | `AWS::Logs::LogGroup` com `RetentionInDays: 7` no template |
| CORS restrito (D-4) | `Access-Control-Allow-Origin` só com a origem configurada, nunca `*`, sem `Allow-Credentials`; teste de feature |
| `APP_DEBUG=false` e sem stack trace em produção | asserções do smoke test |
| `deploy.yml` (OIDC, sem chave de longa duração) | roda `ci.sh`, migra, publica, **smoke test falha o pipeline** |
| Smoke test `scripts/smoke.sh` | confere status, `X-Request-Id`, CORS, `APP_DEBUG` e erro sem stack trace; usado local e no deploy |
| Role de deploy de escopo mínimo | política documentada em `specs/iam/deploy-role.json`, confiança só no repositório e na branch `main` |
| Guardrails de custo | `scripts/custo.sh` (somente leitura), Budgets de US$ 1, `specs/teardown.md` |
| Spikes S5 e S6 fechados | resultado medido em `specs/spikes/` |
| ADR 0004 | aceita **com** a evidência do S6 (o limite de concorrência é 10 e não permite reservar) |
| G0 e G1 | S1 a S7 com número medido; latência medida contra o deploy real |

## Decisões de projeto (propostas, a confirmar)

1. **Um único stage, `prod`**, sem `dev` na nuvem: cada stage duplica função, bucket e log group, e a conta tem orçamento e concorrência (10) mínimos.
2. **Segredos**: `bref-ssm:/devfinder-laravel/prod/...` no `serverless.yml`; o runtime do Bref os lê no cold start. O `${ssm:...}` do Serverless **não serve**: ele grava o valor em texto no template e na configuração da função.
3. **`config:cache` no cold start** (o `bref/laravel-bridge` já faz isso em `/tmp`): **não** rodar `config:cache` no build, senão as variáveis de produção ficam congeladas com os valores do CI.
4. **Banco**: o runtime usa a URL **pooled**; a migration usa a URL **direta**, só do CI (secret do GitHub), nunca da função.
5. **CORS** pela variável `CORS_ALLOWED_ORIGINS` (D-4), tratada no Laravel, porque a Function URL aceita CORS próprio mas não permite lista dinâmica por ambiente no template de forma simples; manter num lugar só.
6. **Concorrência reservada: impossível** nesta conta (S6). A defesa é o limite de 10 da conta, o rate limiting da ADR 0011 (a partir da Fase 4) e o alarme de custo. Isso vai para a ADR 0004 e para o README.

## Fora do escopo da 2b

Qualquer rota das 30 do contrato além de `GET /v1` (Fases 3 a 6); rate limiting em rotas de auth (Fase 4); observabilidade avançada e teste de carga (Fase 7).

## Riscos

- `unaccent`, `pg_trgm` e `uuidv7()` **nunca rodaram no Neon**: a primeira migration real pode falhar. Se falhar, a ADR 0012 tem o critério de reversão.
- O Free Plan encerra a conta ao expirar (ADR 0010): o `teardown.md` precisa ser executável por quem lê, não por quem escreveu.
- OIDC mal escopado dá a qualquer workflow do repositório poder de deploy: confiança restrita a `repo:MarceloVilela@32023347/devfinder-laravel@1402300600:ref:refs/heads/main` (formato imutável do `sub`: o formato antigo `repo:dono/repo:ref:...` foi recusado no primeiro deploy).

## Critério de aceite

- [x] `osls package` + allowlist (job `package` do `ci.yml`), com 14 testes que provam a reprovação (RDS, NAT, API Gateway, Secrets Manager, log sem retenção, segredo em texto, `APP_DEBUG`, CORS `*`). **O job só rodou localmente**; falta vê-lo verde no PR.
- [x] CORS, `X-Request-Id`, `APP_DEBUG=false` e erro sem stack trace provados por `scripts/smoke.sh` **contra o deploy real** (21 checagens, `execucao-fase-2b.log`, seção 12). (Provados só na **simulação local de produção**, `scripts/smoke-local-prod.sh`; o `APP_DEBUG` da função viva é conferido pelo smoke via `aws lambda get-function-configuration`.)
- [x] Deploy feito pelo `deploy.yml` com OIDC (sem `AWS_ACCESS_KEY_ID` em secret), **validado em 2026-10-03** no merge do PR #6 (run 37153911494): migrate, `osls deploy` e smoke verdes. A 1ª tentativa falhou (`sts:AssumeRoleWithWebIdentity`) porque o repositório emite `sub` imutável; corrigida a confiança da role, a política de permissões não precisou de ajuste. O 1º deploy (9 recursos, na allowlist) foi à mão, log seção 13.
- [x] `/health/db` 200 contra o Neon, com migrations aplicadas (extensões testadas: `unaccent`, `pg_trgm`, `uuidv7()`, log seção 9).
- [x] Orçamento de US$ 1: **já existia** (`Orcamento USD 0,01`, inclui créditos); `scripts/custo.sh` rodou (somente leitura) e `specs/teardown.md` está escrito. [ ] 2º orçamento (consumo bruto) **não criado**: depende de autorização.
- [x] S6, G0 e G1 com número medido (`spikes/s5-s6-fase-2b.md`, `spikes/g1-latencia-deploy-real.md`). [x] S5 (OIDC) validado. [ ] ADR 0004 aceita pelo usuário: pendente.
- [ ] PR mergeado (ação do usuário).
