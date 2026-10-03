# Fase 3 — Endpoints públicos de leitura

> Referência: [`../plan.md`](../plan.md), seção 7, Fase 3. Branch `fase-3-leitura-publica`, a partir de `main` (PR #5 mergeado).
> Status: **implementada localmente, aguardando o aceite no deploy real e o PR** (iniciada e implementada em 2026-10-03). Spec antes do código; o aceite se prova em `execucao-fase-3.log`.
> Contrato: [`fase-0-openapi.yaml`](./fase-0-openapi.yaml). Orçamentos de query: [`fase-1-modelo-de-dados.md`](./fase-1-modelo-de-dados.md). Casos: `acceptance/{devs,channels,videos,description,search}.http`.

## Escopo

As 10 rotas públicas de leitura (a 11ª do mapa, `GET /`, já existe desde a 2a):

| # | Rota | Queries (máx.) | Estratégia |
|---|---|---|---|
| 1 | `GET /devs?page=` | 4 | `Queries` direto (sem regra) |
| 2 | `GET /devs/{username}` | 3 (1 se não existe) | `Queries` direto |
| 3 | `GET /channels` | 2 | `Queries` direto |
| 4 | `GET /channels/{searchQuery}` | 2 (1 se não existe) | `Queries` direto |
| 5 | `GET /feed/trending?page=` | 2 | `Queries` direto |
| 6 | `GET /feed/channel?channel_name=&page=` | 3 (1 se o canal não existe) | `Queries` direto |
| 7 | `GET /video/{idYoutubeWatch}` | 1 | `Queries` direto |
| 8 | `GET /description/feed` | 2 | **Action** (monta o texto) |
| 9 | `GET /description/category` | 2 | **Action** (agrupa e monta o texto) |
| 10 | `GET /search?q=` | 2 | **Action** (limites, ordem, `encodeURI`) |

O orçamento é **independente do tamanho da página** (sem N+1) e vira teste com `DB::listen`.

## Decisões desta fase (o contrato ou o v1 não definiam)

| # | Decisão | Motivo |
|---|---|---|
| F3-1 | **Só o caminho anônimo.** O `Authorization` e o `?user=` são ignorados; a personalização (`GET /devs` sem quem já tem like/dislike, `feed/trending?user=`) entra na **Fase 4**, junto com o JWT | Os próprios `.http` marcam esses casos como "Fase 4"; o orçamento "+1 auth" só existe com o middleware |
| F3-2 | **Não encontrado = 200 com `null`** em `/devs/{u}`, `/channels/{q}` e `/video/{id}` (paridade com o v1 e o original). `/feed/channel` com canal inexistente: `{docs:[],total:0,itemsPerPage:30,page:1,totalPages:1}` | `erros-v1.md`; o OpenAPI passa a declarar `nullable` nesses três (correção aqui primeiro) |
| F3-3 | **`page`**: ausente, `0`, negativo, texto ou decimal vira 1; além do fim vira a última (P-3). Nunca 422 | Medido no v1 (Fase 1) |
| F3-4 | **`/search`**: `q` obrigatório, 1 a 100 caracteres depois do `trim` (senão 422); `%`, `_` e `\` literais; 10 canais (por `name` ou `link`) e 20 vídeos (por `title`), canais primeiro; sem caixa e sem acento | `fase-1-modelo-de-dados.md`, D-1; o limite de 100 vem do caso "termo longo" dos `.http` |
| F3-5 | **`/channels/{searchQuery}` aceita `/` no segmento** (`%2F`, ou o link inteiro): `where('searchQuery', '.+')`. O v1 não conseguia (CI4 reparte o path) | Cuidado herdado do v1; permite achar canal por link |
| F3-6 | **Entrada malformada** (byte nulo ou UTF-8 inválido em query ou segmento) responde **400** `{"error":"Malformed input."}`, nunca 500 | O PostgreSQL rejeita `\0` e UTF-8 inválido; seria erro de banco por causa de entrada do cliente |
| F3-7 | Listas de ids (`likes`, `deslikes`, `follow`, `ignore`) em ordem de criação da reação, desempate pelo id | Determinismo (o v1 não definia) |
| F3-8 | Entidade apagada (`deleted_at`) nunca aparece: nem ela, nem reação que aponta para ela, nem vídeo de canal apagado | Fase 1, "Soft delete" |
| F3-9 | `Dev.bio` nulo sai como `""` (como o `DevPresenter` do v1); `Channel.likes/deslikes` sempre `[]` | Paridade |
| F3-10 | Datas em UTC, `+00:00` (ISO 8601 com offset), qualquer que seja o fuso da sessão do banco | Paridade com `to_iso8601` do v1 |

## Estrutura

```
app/Shared/{Pagination/{Page,Paginated}, Http/Requests/PageRequest, Http/Middleware/RejectMalformedInput,
            Exceptions/MalformedInput, Support/{NormText,Uri}}
app/Features/Dev/{Data/DevView, Queries/DevQueries, Http/{Controllers,Resources}}
app/Features/Channel/{Models/{Channel,Tag}, Queries/ChannelQueries, Http/{Controllers,Resources}}
app/Features/Video/{Data/VideoView, Queries/VideoQueries, Http/{Controllers,Requests,Resources}}
app/Features/Description/{Actions, Queries, Http/Controllers}
app/Features/Search/{Actions, Data, Queries, Http/{Controllers,Requests,Resources}}
```

- Entrada: Form Request → número de página ou DTO `readonly` (`SearchData`). Saída: `JsonResource` com o shape do schema.
- Consulta de leitura feita à mão (`DB::table`) inclui `deleted_at IS NULL` na tabela **e** nos JOINs (índices parciais).
- Texto sempre por `norm_text(coluna) = norm_text(?)` (índices da Fase 1); o termo nunca entra na SQL por concatenação.
- Regra nova no teste de arquitetura: controller não importa `Models` (desbloqueada pelo primeiro Model).

## Testes (spec → teste → implementação)

| Camada | O que prova |
|---|---|
| Feature (PostgreSQL real, dataset de paridade: 35 devs, 3 canais, 55 vídeos) | totais, paginação (1, 2, `0`, `-1`, `abc`, `1.5`, `999`), ordem, não encontrado, soft delete, `X-Request-Id` |
| Contrato (Gesso) | cada rota contra o OpenAPI, inclusive os casos `null` |
| Orçamento de queries | cada rota com página cheia e página parcial: mesmo número, dentro do orçamento |
| Segurança | `/search` com `'`, `"`, `%`, `_`, `\`, `(a+)+$`, 1000 caracteres, `\0`, UTF-8 inválido; nome de coluna em `page`; `%2F` no segmento |
| Unidade | `Page`, `Uri::encode` (igual ao `encodeURI` do JS), `NormText`, texto das descrições |
| Arquitetura | regras da 2a + controller sem Models |

## Fora do escopo

Personalização por token (Fase 4); `GET /feed/subscriptions` e todas as escritas (Fases 4 a 6); rate limiting (Fase 4).

## Critério de aceite

- [x] Casos de aceite passam **local** (`execucao-fase-3.log`, seções 2 a 5: as 30 capturas dos `.http` de leitura e os 7 de `search.http`). [ ] **No deploy real**: pendente, depende de autorizar o deploy e o seed do Neon.
- [x] G3: diff normalizado v1 × `php-laravel` nulo para as rotas 1 a 9 (30 capturas, status e content-type iguais; a 10 é validada contra o contrato). O G3 achou uma lacuna do dataset (`description` e `avatar` dos canais, `thumbnail` dos vídeos), corrigida no seeder e em `dataset-de-paridade.md`.
- [x] Orçamento de queries respeitado e testado (`QueryBudgetTest`); `preventLazyLoading` ligado.
- [x] `scripts/ci.sh` verde (Pint, Larastan nível 9, 179 testes Pest com arquitetura e contrato) e reproduzido em container limpo (`scripts/reproduz-ci.sh`, exit 0). [ ] Falta ver o workflow verde no PR.
- [ ] PR mergeado (ação do usuário).
