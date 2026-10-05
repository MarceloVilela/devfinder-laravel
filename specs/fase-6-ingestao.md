# Fase 6 — Ingestão em lote (agendada)

> Referência: [`../plan.md`](../plan.md), seção 7, Fase 6. Branch `fase-6-ingestao`, a partir de `main` (PR #9 mergeado).
> Status: **implementada localmente, com SSM e IAM prontos na conta; aguardando o PR, o deploy e o aceite da execução agendada** (iniciada e implementada em 2026-10-04). Spec antes do código; o aceite se prova em `execucao-fase-6.log`.
> Referências lidas: `devfinder-api` (`task.ts`, `VideoRefreshController`), `serverless` (`fase-6-ingestao-lote.md`, function `videoRefresh`) e
> `php-codei` (`VideoIngestor`, `video:refresh`, `video-refresh.yml`). Contrato: [`fase-0-openapi.yaml`](./fase-0-openapi.yaml).

## Escopo

| # | Entrega | Estratégia |
|---|---|---|
| 1 | `POST /video/refresh` (a 29ª das 30 operações) | rota autenticada que chama a Action |
| 2 | Comando `php artisan video:refresh` | busca candidatos no JSONBin (ou numa fixture) e chama a **mesma** Action |
| 3 | Agendamento a cada 12 h | função Lambda de console (Bref) disparada por uma regra do EventBridge |
| 4 | Resiliência | timeout, retry com backoff e `Retry-After` no JSONBin e no GitHub; falha definitiva registrada |

Fora do escopo: ingestão de canais (decisão herdada do v1 e do `serverless`); `POST /channels/refresh`.

## Regra da Action `IngestVideos` (paridade com o `VideoIngestor` do v1)

Para cada candidato `{title, url, channel, channel_url, thumbnail}`:

1. `&pp=…` sai de `url` e de `thumbnail`.
2. Canal por `name = channel` **ou** `link = channel_url` **ou** `alternative_link = channel_url`, exatos (sem caixa e sem acento). O caso real que
   motiva o "ou nome": o canal com `link` no formato antigo (`/channel/UC…`) e vídeos que trazem `channel_url` no formato `@handle`.
   Não achou: item em `errors` com o `errorMessage` de `POST /video` e os campos enviados.
3. `url` já existe: o vídeo existente vai em `videosFounded`, nada é gravado.
4. Senão grava e o vídeo vai em `videosAdded`. Thumbnail: `hq720_custom_N` (frame assinado que costuma quebrar) vira o `hqdefault` do mesmo id; vazia vira
   `https://i.ytimg.com/vi/<id>/hqdefault.jpg`; qualquer outra fica como veio.

Resposta: `{videosAdded, videosFounded, errors}`, cada vídeo no schema `Video`, na ordem dos candidatos.

## Decisões desta fase

| # | Decisão | Motivo |
|---|---|---|
| F6-1 | **Uma Action só** (`Video\Actions\IngestVideos`) para a rota e o comando; nenhuma regra no controller nem no comando | Plano; lição do `serverless`, que precisou refatorar depois |
| F6-2 | **`channel_name` do bin vira `channel` na borda** (no comando), antes da Action; o contrato HTTP já usa `channel` | Achado herdado de `serverless` e `php-codei` |
| F6-3 | **Robustez que o v1 não tinha** (divergência D-17): candidato sem `v=`, com id inválido ou com texto grande demais vira item em `errors` (o v1 gravava lixo ou dava 500); o mesmo `url` duas vezes no lote: o segundo é `videosFounded`; violação de `UNIQUE` (corrida, ou outro `url` com o mesmo id) também é `videosFounded`; **cada candidato é isolado** (savepoint): um que falha não derruba o lote | Idempotência e erro por item, como pede o plano |
| F6-4 | **Falha de banco aborta o lote**: 3 candidatos seguidos com erro de conexão interrompem a execução com exceção (nada de lote inteiro "com erro" silencioso) | Evita centenas de falhas iguais e um resumo enganoso |
| F6-5 | **Queries**: canais resolvidos uma vez por par `(channel, channel_url)` distinto; `url`s existentes checadas em blocos de 200; leitura dos vídeos para a resposta em blocos. Orçamento: `canais distintos + inserções + 4` | A estimativa da Fase 1 (`1 + 4 por item`) decidiu, na Fase 6, pré-carregar o que dá |
| F6-6 | **`POST /video/refresh`**: exige `auth` e papel **`ADMIN`** (como `POST /video`, F5-15), limite de escrita por dev; `record` ausente ou vazio dá 200 com listas vazias (paridade com o v1); no máximo **200 candidatos** por chamada (422 acima disso: o Lambda web tem 28 s) | RBAC; limite do Lambda; `.http` do v1 |
| F6-7 | **Comando `video:refresh`**: fonte é o JSONBin (`JSONBIN_API_KEY`, `JSONBIN_ID_SUBS`) ou `--fixture=<arquivo>` no mesmo formato do bin. Sem credenciais e sem fixture: avisa "nada a fazer" e sai com 0 (como o v1). Formato inesperado ou fonte indisponível depois dos retries: **exit 1** e erro no log. A saída é a do v1 (`Adicionados: X \| Já existiam: Y \| Erros: Z` e a lista de `errorMessage`) e o mesmo resumo vai para o log estruturado | Paridade; a execução agendada precisa ser observável |
| F6-8 | **`ResilientHttp`** (em `Shared\Http`): até 3 tentativas, espera de 0,5 s dobrando, respeitando `Retry-After` (segundos ou data, no máximo 10 s), para erro de conexão e para 429, 502, 503 e 504; timeout de 10 s no JSONBin. Usado pelo cliente do JSONBin e pelo do GitHub (que só repetia erro de conexão). A chave do JSONBin vai só no cabeçalho `X-Master-Key` e nunca em mensagem de erro ou log | Plano; lição do `transcript` |
| F6-9 | **Agendamento por regra do EventBridge** (`schedule` do Serverless, `rate(12 hours)`), não pelo EventBridge Scheduler do plano: a regra agendada não tem custo, vem pronta no Serverless e precisa de menos recursos (sem role de execução do Scheduler). Entra `AWS::Events::Rule` na allowlist e a role de deploy ganha `events:*` limitado ao prefixo `devfinder-laravel-prod-`. Função própria `refresh` (runtime `php-84-console` do Bref, 300 s, 1024 MB), sem URL; o `web` não ganha variável do JSONBin | G2 (custo e allowlist); ADR 0014 |
| F6-10 | **Segredos do JSONBin no SSM** (`jsonbin-api-key`, `jsonbin-id-subs`), só na função `refresh`; o *guard* do `deploy.yml` passa a exigi-los | Fail-closed, como nos segredos da Fase 4 |
| F6-11 | **Fixture congelada e sintética** (`tests/Fixtures/jsonbin-videos.json`, no formato do bin, com `channel_name`): é a entrada do comando nos testes e da comparação com o v1 (que recebe os mesmos candidatos por `POST /video/refresh`, porque o `video:refresh` do v1 só lê o JSONBin real). Contra o JSONBin real só se confere que o resumo mantém o formato | Plano: o bin real muda com o tempo |

## Estrutura

```
app/Shared/Http/ResilientHttp
app/Features/Video/{Actions/{IngestVideos, ResolveThumbnail}, Data/{IngestResult, Candidate}, Console/RefreshVideos,
                    Integrations/{JsonBinClient, HttpJsonBinClient}, Exceptions/JsonBinUnavailable, Http/{Controllers/IngestVideosController, Requests/IngestVideosRequest}}
tests/Fixtures/jsonbin-videos.json
```

## Testes (spec → teste → implementação)

| Camada | O que prova |
|---|---|
| Feature, Action | os três caminhos do v1 (novo, duplicado, canal inexistente) com números iguais; canal por nome, por link e por link alternativo; `&pp=`; thumbnail (`hq720_custom_N`, vazia, mantida); URL repetida no lote; corrida de `UNIQUE`; candidato inválido (sem `v=`, id longo, texto grande, não é objeto); isolamento por candidato (uma falha forçada no banco não derruba o resto); aborto por falha de conexão; **idempotência** (rodar duas vezes: 0 adicionados e todos encontrados) |
| Feature, `POST /video/refresh` | 401, 403 para `USER`, 200 com lote misto no formato do contrato, `{}` e `record: []`, 422 acima de 200, orçamento de queries, limite de escrita |
| Feature, comando | fixture: resumo e código de saída; segunda execução idempotente; sem credenciais; fonte fora do ar → exit 1 e log; JSONBin falso (formato do bin com `channel_name`) |
| Feature, `ResilientHttp` e clientes | sucesso, 429 e 5xx com retry, `Retry-After` honrado (espera conferida com `Sleep::fake()`), falha definitiva, erro de conexão, chave nunca no log, mesmo comportamento no cliente do GitHub |
| Contrato (Gesso) | `POST /video/refresh` 200, 401, 403 e 422 |
| Infra | allowlist com `AWS::Events::Rule` (e teste que reprova regra sem o prefixo), `osls package` com as duas funções, segredos do JSONBin só por `bref-ssm:` |
| G3 | v1 local × `php-laravel` local com os mesmos candidatos por `POST /video/refresh`: resumo e vídeos iguais depois de normalizar |
| Arquitetura | Action sem Http; integração só usada por Action e comando |

## Parte automática × manual

Tudo roda no CI com fake do JSONBin. O JSONBin real e a execução agendada dependem de **credenciais suas** (as do `php-codei/.env`, mesmo bin do original), **gravadas no SSM em 2026-10-04**,
(`/devfinder-laravel/prod/jsonbin-api-key` e `jsonbin-id-subs`) e de uma alteração da política da role de deploy (IAM). A prova do agendamento é a saída no
CloudWatch (log da função `refresh` com o resumo), não "acho que disparou".

## Critério de aceite

- [x] Testes (474), Pint, Larastan nível 9 e CI em container limpo verdes; idempotência e orçamento de queries provados (`execucao-fase-6.log`, seção 1). [ ] Falta ver o workflow verde no PR.
- [x] Resumo no mesmo formato e números iguais aos do v1 para a mesma fixture (G3, via `POST /video/refresh`): 9 capturas iguais (seção 4).
- [x] `POST /video/refresh` e `video:refresh` passam local; `video:refresh --fixture` roda no container (seção 5).
- [x] Função `refresh` em produção com o bin real, disparada à mão: 50 candidatos, 39 adicionados, 2 já existiam, 9 erros (canais sem cadastro); **reexecução idempotente** (0 adicionados, 41 encontrados) (`execucao-fase-6.log`, seção 9). [ ] Falta a execução **disparada pela regra** do EventBridge, comprovada no CloudWatch.
- [ ] ADR 0014 e divergência D-17 aprovadas. [x] Allowlist atualizada (`AWS::Events::Rule`, com testes) e política da role no `deploy-role.json`; [x] política aplicada na conta em 2026-10-04 (`RegraAgendadaDaIngestao`, conferida com `get-role-policy`) e os dois parâmetros do SSM criados.
- [ ] PR mergeado.
