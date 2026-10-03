# Fase 1 — Modelo de dados (PostgreSQL) e orçamentos de query

> Referência: [`../plan.md`](../plan.md), Fase 1. Ponto de partida: `../../php-codei/specs/fase-1-data-model.md` (MySQL), adaptado para
> PostgreSQL 18 (Neon). Contrato: [`fase-0-openapi.yaml`](./fase-0-openapi.yaml). ADR: [`0012`](./adr/0012-modelo-de-dados.md).
> Status: **aprovada pelo usuário em 2026-10-03** (decisões P-1 a P-6, D-10 e D-11, e ADR 0012 aceitas). Falta só o PR mergeado.
> Evidência: [`fase-1-validacao.sql`](./fase-1-validacao.sql) e [`fase-1-validacao.log`](./fase-1-validacao.log), rodados em PostgreSQL 18
> com as migrations de [`../database/migrations/`](../database/migrations/) aplicadas pelo `artisan migrate` (Laravel 13 descartável do spike).

## O que mudou em relação ao v1

| Ponto | v1 (MySQL) | Aqui (PostgreSQL) | Motivo |
|---|---|---|---|
| Soft delete | `deleted_at` em 4 tabelas, `UNIQUE` pleno (nome de apagado ocupava a chave) | `deleted_at` mantido (**P-1, decidida**) e `UNIQUE` **parcial** `WHERE deleted_at IS NULL` | Decisão do usuário. O Postgres tem índice parcial, então o apagado libera a chave (limitação do MySQL removida) |
| Unicidade de `name`/`link`/`username` | `UNIQUE` com collation `_ai_ci` (sem caixa nem acento) | `UNIQUE` parcial em `norm_text(coluna)` (sem caixa **e sem acento**) | P-4: função `norm_text` = `lower(unaccent())` (extensão `unaccent`), equivalente ao `_ai_ci`. Postgres tem índice de expressão. |
| `ENUM` de `type` | `ENUM` nativo | `ENUM` nativo do Postgres (P-6) | Tipos `dev_reaction_type` e `channel_reaction_type`; incluir valor novo exige `ALTER TYPE ... ADD VALUE` (migration própria). |
| Busca | `LIKE` sem índice | `LIKE` sobre `norm_text()` com índice GIN `pg_trgm` (P-5) | Mesma busca sem caixa nem acento do v1, com índice. |
| `ON UPDATE CASCADE` nas reações | `RESTRICT` (InnoDB proíbe `CHECK` + `CASCADE`) | padrão (`NO ACTION`) | A restrição era do MySQL; a PK nunca muda. |
| Ids | inteiro autoincremento | UUIDv7 (P-2) | Decisão do usuário; ordem por id continua cronológica |
| Datas | `DATETIME` sem fuso | `timestamptz` | Fuso explícito; o JSON sai em UTC. |
| Ordenação | só `created_at DESC` | `created_at DESC, id DESC` | Desempate determinístico (o v1 não define a ordem em empates de `created_at`). |
| `limit` na paginação | nenhum | nenhum | O contrato só tem `page`, 30 fixo. O plano dizia `page`/`limit`: corrigido. |

## Esquema (7 tabelas)

Ids: **UUIDv7** (`uuid`, default `uuidv7()` nativo do PostgreSQL 18; P-2 e D-10), ordenáveis por tempo, gerados no banco (funciona com `insertOrIgnore`). No JSON são string (D-7). Models com `$incrementing = false` e `$keyType = 'string'`. Timestamps `timestamptz` não nulos
(`created_at`/`updated_at`), exceto nas reações (só `created_at`). As 4 tabelas de entidade têm `deleted_at timestamptz` nulo (soft delete). Todos os índices únicos e de listagem dessas tabelas são **parciais** (`WHERE deleted_at IS NULL`).

| Tabela | Colunas | Constraints e índices |
|---|---|---|
| `devs` | `id` uuid, `username` (190), `name` (255), `bio` text nulo, `avatar` (500), timestamps, `deleted_at` | **UNIQUE `norm_text(username)`**; índice `(created_at desc, id desc)` para `GET /devs` |
| `channels` | `id` uuid, `name` (255), `link` (500), `alternative_link` (500, nulo), `user_github` (190, nulo), `description` text nulo, `category` (190), `avatar` (500, nulo), timestamps, `deleted_at` | **UNIQUE `norm_text(name)`** e **`norm_text(link)`**; índice `norm_text(alternative_link)` (resolução de canal na escrita; **não único**); índice `(category, norm_text(name))`; **GIN trigram** em `norm_text(name)` e `norm_text(link)` |
| `tags` | `id` uuid, `name` (100), `deleted_at` | `UNIQUE (name)` parcial |
| `channel_tag` | `channel_id`, `tag_id` | PK `(channel_id, tag_id)`; FK `CASCADE` nas duas; índice `tag_id` |
| `videos` | `id` uuid, `youtube_id` (20), `title` (500), `url` (500), `channel_id`, `thumbnail` (500), `viewnum` int nulo, `published_at` nulo, timestamps, `deleted_at` | `UNIQUE (youtube_id)`, `UNIQUE (url)`; FK `channel_id` **`RESTRICT`**; índices `(created_at desc, id desc)` e `(channel_id, created_at desc, id desc)` |
| `dev_reactions` | `dev_id`, `target_dev_id`, `type`, `created_at` | PK `(dev_id, target_dev_id, type)`; `type` é `ENUM dev_reaction_type ('like','dislike')`; **`CHECK dev_id <> target_dev_id`**; FK `CASCADE` |
| `channel_reactions` | `dev_id`, `channel_id`, `type`, `created_at` | PK `(dev_id, channel_id, type)`; `type` é `ENUM channel_reaction_type ('follow','ignore')`; FK `CASCADE` |

Notas:
- **Soft delete**: Eloquent `SoftDeletes` nos 4 models de entidade (o escopo global filtra `deleted_at`); consultas escritas à mão (`DB::`, `JOIN`) devem incluir `deleted_at IS NULL`, senão não usam o índice parcial e devolvem apagados. Tabelas de relação (`channel_tag`, `dev_reactions`, `channel_reactions`) fazem `DELETE` real: desfazer reação é o próprio significado. `videos.channel_id` mantém `RESTRICT` como rede contra `forceDelete` de canal com vídeos. Reações de um dev ou canal apagado seguem no banco; as consultas de leitura só listam entidades ativas (JOIN com `deleted_at IS NULL`). Provado em `fase-1-validacao.log`, seção 2 (apagado libera `username`, `name`, `link`, `youtube_id`; o ativo continua protegido).
- `videos` não tem `channel`, `channel_url` nem `channel_icon`: o Video do contrato sai de `JOIN channels` (`channel` = `channels.name`, `channel_url` = `channels.link`).
  `channel_icon` foi removido de vez (0/500 no dump do v1 e nada escreve nele). `published_at` sai como `date`; `viewnum` fica por paridade (sempre nulo).
- `likes`/`deslikes` de **Canal** são sempre `[]` (como no v1: não há tabela, nada escreve). O serializer devolve `[]` constante.
- `like` e `dislike` (e `follow` e `ignore`) são **independentes**: a PK inclui `type`, então o mesmo par pode ter os dois (comportamento herdado do Mongo e do v1).
- Sem índice em `dev_reactions.target_dev_id`: nenhuma consulta do contrato filtra por ele e nenhum fluxo apaga dev (o `CASCADE` só roda em limpeza administrativa).
- `channels.alternative_link` existe porque 85% dos canais reais o têm e três pontos de código do original deduplicam por `name`/`link`/`alternative_link`.
- Dados reais **não** são importados (marca e LGPD). Se um dia forem, os nomes e links colidentes do dump (4 nomes e 3 links) exigem dedup prévia por causa dos `UNIQUE`.

## Escrita concorrente (o que o banco garante)

- **Reação** (like, dislike, follow, ignore): `insertOrIgnore` (`INSERT … ON CONFLICT DO NOTHING`) e `DELETE` pela PK composta. Idempotente e atômico por linha;
  elimina a race condition de `push`/`splice` + `save` do Mongo. Provado em `fase-1-validacao.log`, seção 2.
- **Find-or-create de dev** (`POST /devs`, callback do OAuth): `insertOrIgnore` e depois `select` por `norm_text(username)`. Corrida entre duas requisições vira um só registro.
- **Canal e vídeo**: dedup de aplicação primeiro (resposta amigável, 409 ou 400 do contrato); a violação de `UNIQUE` (`UniqueConstraintViolationException`) é a rede de segurança e vira
  o mesmo erro tipado, nunca 500. `POST /channels` em **transação** (`channels` + `channel_tag`); `POST /video/refresh` **sem** transação de lote (erros por item, como no v1).
- Migrations rodam pela URL **direta** do Neon e o runtime pela **pooled** (S4: prepared statements e `SET` funcionam no modo transação).

## Paginação (decisão registrada, não herdada por acaso)

`page` em query string, **30 por página fixo**, resposta `{docs, total, itemsPerPage}`. Comportamento do v1 medido em 2026-10-03 (`GET /devs?page=…`, 35 devs):

| `page` | v1 devolve | Aqui (P-3) |
|---|---|---|
| ausente, `0`, `-1`, `abc`, `1.5` | página 1 (30 itens) | página 1 |
| `2` | 5 itens | 5 itens |
| `3`, `999` (além da última) | **última página** (5 itens, clamp) | última página |

Resposta: `{docs, total, itemsPerPage, page, totalPages}`. Os três primeiros são idênticos ao esperado; `page` (página servida) e `totalPages` são **campos novos** (P-3, D-11, já no OpenAPI). Com `page`, o cliente sabe que o clamp serviu a última página e para.

`total` = `COUNT(*)` da consulta filtrada; página = `LIMIT 30 OFFSET (p-1)*30`. Offset linear é aceito na escala do projeto (medido: offset 3.990 em 4.000 devs, 0,5 ms).
Pedir além do fim continua devolvendo a última página (comportamento do v1), agora com `page` igual à última.


## Busca (`GET /search`)

Consulta parametrizada, **nunca** regex do usuário (D-1). Termo com `%`, `_` e `\` escapados (`norm_text(coluna) LIKE norm_text(:padrão) ESCAPE '\'`; provado: `_` literal acha 0, sem escape acharia 55). Sem caixa e sem acento, como o v1.
`q` ausente ou vazio: 422. Resultado: canais por `name` ou `link`, vídeos por `title`; limites **10 canais e 20 vídeos** (proposta; o original não tinha limite), canais primeiro.
Formato do original: canal `{value: encodeURI(name), label: name, type: "channel"}`; vídeo `{value: encodeURI(youtube_id), label: title, type: "video"}`.

| Alternativa | Medido (50.000 vídeos, 2.000 canais; 100x o v1) | Decisão |
|---|---|---|
| `ILIKE` sem índice | vídeos 48 a 66 ms, canais 3 a 5 ms (seq scan) | descartada (era a proposta; o usuário escolheu o índice) |
| `pg_trgm` + GIN em `norm_text(title)`, `norm_text(name)`, `norm_text(link)` | vídeos **0,18 ms**, canais **0,09 ms** (termo de 4 caracteres) | **adotada (P-5)**. Custo: 3 índices GIN e as extensões `pg_trgm` e `unaccent` (o Neon suporta). Termo com menos de 3 caracteres não usa o índice (varredura; a consulta tem `LIMIT`); a Fase 3 pode exigir `q` com 3+ caracteres |
| Full-text | não medido | descartada: não casa substring dentro da palavra, e o original é "contém" |

## Orçamento de queries por operação (vira teste na Fase 3)

"Auth" = +1 query para carregar o dev do token (rotas autenticadas, ou com token válido em rota de autenticação opcional). O orçamento é **independente do tamanho da página**
(sem N+1: reações e tags vêm por `whereIn` da página).

| # | Operação | Queries | Resolução |
|---|---|---|---|
| 1 | `GET /` | 0 | resposta fixa |
| 2 | `GET /devs` | 4 (+1 auth) | `COUNT`, página, `dev_reactions` da página, `channel_reactions` da página. Com auth: `WHERE id <> :eu AND id NOT IN (SELECT target_dev_id FROM dev_reactions WHERE dev_id = :eu AND type IN ('like','dislike'))` dentro da contagem e da página (só like/dislike excluem, como no v1) |
| 3 | `GET /devs/{username}` | 3 | dev por `norm_text(username)`, `dev_reactions`, `channel_reactions`. Inexistente: **200 com `null`** (comportamento do v1, conferir no `.http` da Fase 3) |
| 4 | `GET /channels` | 2 | canais `ORDER BY norm_text(name)` (sem paginação, como no v1) + tags de todos por `JOIN channel_tag/tags` |
| 5 | `GET /channels/{searchQuery}` | 2 | `norm_text(name) = norm_text(:q) OR norm_text(link) = norm_text(:q) OR norm_text(alternative_link) = norm_text(:q)` (exato) + tags. Plano com os 3 índices (BitmapOr), 0,1 ms |
| 6 | `GET /description/feed` | 2 | página de trending com `JOIN channels` + tags dos canais dessa página. O v1 fazia N+1 aqui |
| 7 | `GET /description/category` | 2 | canais `ORDER BY category, norm_text(name)` + tags |
| 8 | `GET /feed/trending` | 2 | `COUNT` + página com `JOIN channels`, `ORDER BY created_at DESC, id DESC` (índice, 0,26 ms em 50.000) |
| 9 | `GET /feed/channel?channel_name=` | 3 | canal por nome exato, `COUNT`, página por `channel_id` (índice composto) |
| 10 | `GET /feed/subscriptions` (auth) | 3 (inclui auth) | `COUNT` + página com `JOIN channel_reactions` (`type='follow'`), mesma ordem. 3,5 ms para 50 canais seguidos em 50.000 vídeos |
| 11 | `GET /video/{idYoutubeWatch}` | 1 | `videos JOIN channels WHERE youtube_id = :id` (índice único) |
| 12 | `GET /search` | 2 | canais (`LIMIT 10`) e vídeos (`LIMIT 20`), `LIKE` escapado sobre `norm_text()` (GIN) |
| 13 | `GET /me` | 3 | dev (token), `dev_reactions`, `channel_reactions` |
| 14–15 | `GET /likes/devs`, `GET /dislikes/devs` | 4 | auth, devs alvo por `JOIN dev_reactions`, e as reações desses devs (2) |
| 16 | `GET /auth/github` | 0 | redirect |
| 17 | `GET /auth/github/callback` | ≤ 3 | `insertOrIgnore` + `select` do dev (mais a chamada externa ao GitHub) |
| 18–25 | `POST`/`DELETE` de `/likes/{devs,channels}/{u}` e `/dislikes/{devs,channels}/{u}` | ≤ 5 | auth, alvo por username/nome, `insertOrIgnore` ou `DELETE`, reações do dev para a resposta (2) |
| 26 | `POST /devs` | ≤ 6 | auth, `insertOrIgnore`, `select`, reações (2) e a chamada externa ao GitHub. Sempre 201, mesmo se já existir (v1) |
| 27 | `POST /channels` | ≤ 9, em transação | auth, dedup, insert, tags (`insertOrIgnore` + `select`), `channel_tag`, e o dev de `userGithub` (find-or-create + GitHub) |
| 28 | `POST /video` | ≤ 5 | auth, canal, existência, insert, e o Video com `JOIN` |
| 29 | `POST /video/refresh` | ≤ 1 + 4 por item | auth + por item (canal, existência, insert). O lote cresce com o payload; pré-carregar canais do lote é decisão da Fase 6 |

Fora do escopo: `POST /channels/refresh` (A1). Total mapeado: 29 operações.

## Decisões (todas do usuário, 2026-10-03)

| # | Decisão | Alternativa |
|---|---|---|
| P-1 | **Decidida pelo usuário (2026-10-03): manter `deleted_at`**, com índices únicos parciais | (descartada) sem soft delete |
| P-2 | **Decidida pelo usuário (2026-10-03): UUIDv7** (D-10) | (descartada) `bigint` |
| P-3 | **Decidida pelo usuário (2026-10-03)**: formato `{docs,total,itemsPerPage}` mantido, com `page` e `totalPages` a mais (D-11); `page` fixo 30, inválido vira 1, além do fim vira a última | (descartada) `docs: []` além do fim |
| P-4 | **Decidida pelo usuário (2026-10-03): alternativa `unaccent`**: unicidade e busca por nome sem caixa e sem acento, via `norm_text()` | (descartada) só `lower()` |
| P-5 | **Decidida pelo usuário (2026-10-03): alternativa `pg_trgm` + GIN já agora** (limites 10 canais e 20 vídeos mantidos) | (descartada) `ILIKE` sem índice |
| P-6 | **Decidida pelo usuário (2026-10-03): alternativa `ENUM` nativo** | (descartada) `text` + `CHECK` |

## Critério de aceite (de `plan.md`)

- [x] Decisões P-1 a P-6 tomadas pelo usuário (2026-10-03).
- [x] Spec aprovada e ADR 0012 aceita pelo usuário (2026-10-03).
- [x] Migrations escritas para cada uma das 7 tabelas e **reversíveis**: `migrate`, `migrate:rollback` e `migrate` de novo, sem erro (PostgreSQL 18).
- [x] Cada operação do OpenAPI no escopo mapeada a uma query com orçamento (29 de 29, tabela acima).
- [x] Constraints provadas por SQL (`fase-1-validacao.log`, seção 2) e planos de consulta conferidos em escala 100x (seção 5).
- [ ] PR mergeado.

## Pendências levadas para as próximas fases

- Fase 2a: seeder do dataset de paridade (35 / 3 / 55) em `database/seeders/`; a pasta `database/migrations/` deste PR é mantida quando o Laravel for criado na raiz.
- Fase 3: o orçamento acima vira teste (`DB::listen` com contagem máxima) para cada operação de leitura, com 31+ itens na lista.
- Fase 3: comportamento de "não encontrado" de `GET /channels/{q}` e `GET /feed/channel` confirmado no oráculo v1.
