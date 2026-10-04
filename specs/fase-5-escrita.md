# Fase 5 — Endpoints autenticados de escrita e relacionamento

> Referência: [`../plan.md`](../plan.md), seção 7, Fase 5. Branch `fase-5-escrita`, a partir de `main` (PR #8 mergeado).
> Status: **implementada localmente; D-14 adiada, aguardando o aceite no deploy real e o PR** (iniciada e implementada em 2026-10-04). Spec antes do código; o aceite se prova em `execucao-fase-5.log`.
> Contrato: [`fase-0-openapi.yaml`](./fase-0-openapi.yaml). Orçamentos: [`fase-1-modelo-de-dados.md`](./fase-1-modelo-de-dados.md). Casos: `acceptance/{devs,channels,videos,reactions}.http`.

## Escopo (14 operações)

| # | Operação | Resposta | Queries (máx., inclui o `auth`) |
|---|---|---|---|
| 1 | `POST /devs` | 201 Dev (sempre, mesmo se já existe) | 6 |
| 2 | `POST /channels` | 201 canal novo, 200 canal atualizado | 8 (10 com `userGithub`; 9 ao atualizar), em transação |
| 3 | `POST /video` | 201 Video; 400 e 409 com `errorMessage` | 5 (6 no 409) |
| 4–7 | `POST /likes/devs/{u}`, `/dislikes/devs/{u}`, `/likes/channels/{name}`, `/dislikes/channels/{name}` | 200 Dev autenticado | 5 |
| 8–11 | `DELETE` dos quatro acima | 200 Dev autenticado | 5 |
| 12–13 | `GET /likes/devs`, `GET /dislikes/devs` | 200 array de Dev | 4 |
| 14 | `GET /feed/subscriptions` | 200 `{docs,total,itemsPerPage,page,totalPages}` | 3 |

Todas exigem `auth` (Fase 4). **`POST /channels` e `POST /video` exigem ainda o papel `ADMIN`** (RBAC, F5-15). Fora do escopo: `POST /video/refresh` e `POST /channels/refresh` (Fase 6 e fora do escopo).

## Decisões do usuário (2026-10-04), registradas com o risco

| # | Decisão | Risco aceito |
|---|---|---|
| F5-1 | **`POST /channels` acha o canal existente por "contém"**, como o v1 e o original: `norm_text(name) LIKE %título%` ou `norm_text(link) LIKE %link%` (sem caixa e sem acento, `%`/`_`/`\` literais) | Um título curto casa e **atualiza** qualquer canal cujo nome o contenha (ex.: "Tech" atualiza "Tech Brasil") |
| F5-2 | ~~Qualquer dev autenticado atualiza qualquer canal por `POST /channels` (sem Policy de dono)~~ **Substituída pela F5-15 (RBAC), a pedido do usuário em 2026-10-04** | O risco (qualquer conta do GitHub sobrescrever canal) some para quem é `USER`; segue valendo para `ADMIN` |

## Decisões de projeto (propostas, a confirmar)

| # | Decisão | Motivo |
|---|---|---|
| F5-3 | **Like, dislike, follow e ignore são independentes** (o mesmo par pode ter os dois; só `insertOrIgnore` e `DELETE` pela PK composta). "Os dois lados do par" do plano é o par `POST`/`DELETE` de cada endpoint | `devfinder-api` e v1 são independentes (lidos em 2026-10-04); Fase 1 |
| F5-4 | **Auto-like é no-op**: `POST /likes/devs/<o próprio>` devolve 200 sem gravar (a `CHECK dev_id <> target_dev_id` da Fase 1 proíbe) | v1 (`DevReactionModel::add`) |
| F5-5 | **Alvo de reação**: dev por `norm_text(username)`, canal por `norm_text(name)` exato (não aceita link). Inexistente ou apagado: 400 `{"error":"Dev not exists"}` / `{"error":"Channel not exists"}` | `erros-v1.md`, v1 |
| F5-6 | **`GET /likes/devs` e `/dislikes/devs`**: devs-alvo ativos, sem o próprio, em ordem de id (UUIDv7 = ordem de criação, como o id crescente do v1), cada um com as suas reações | v1, original |
| F5-7 | **`POST /devs`**: `username` obrigatório e no formato de login do GitHub (`^[A-Za-z0-9](?:[A-Za-z0-9-]{0,38})$`, senão 422: o valor entra na URL do GitHub, então `../`, `/` e espaços não passam). Existente (sem caixa): 201 com o dev, **sem chamar o GitHub**. Novo: busca o perfil público; **404** `{"error":"GitHub user not found."}` se não existe (D-6), **502** `{"error":"GitHub unavailable."}` se o GitHub falha; grava `username` em minúsculas | D-6, D-3, A7 |
| F5-8 | **`POST /channels`**: `link`, `title` e `category` obrigatórios; `description`, `avatar`, `tags` (lista de textos de até 100) e `userGithub` (formato de login) opcionais; 422 no resto (D-3). `category` perde emoji (`U+E000–F8FF` e `U+1F300–1F5FF`, **sem** cortar espaço); tags **substituídas** por inteiro, sem repetir; `userGithub` vazio vira `null`. Tudo (canal, tags, vínculos) em **uma transação** | v1, original, Fase 1 |
| F5-9 | **Dev de `userGithub`** só na **criação** do canal, por `Action` que usa `GithubClient`, nunca no controller. **Falha do GitHub (404 ou fora do ar) não derruba a criação do canal**: o canal sai 201 sem o dev e o motivo vai para o log | v1 (`http_errors false`); A7 |
| F5-10 | **`POST /video`**: `title`, `url`, `channel`, `channel_url` obrigatórios, `thumbnail` opcional; `&pp=…` sai de `url` e de `thumbnail`; o id vem de `v=` (1 a 20 caracteres `[A-Za-z0-9_-]`; sem `v=` ou fora disso: **422**, o v1 quebrava); canal por `name = channel` **ou** `link = channel_url` **ou** `alternative_link = channel_url`, exatos (400 `errorMessage` se não existe); `url` já existente: 409 com `errorMessage` e o vídeo; `thumbnail` vazia vira `https://i.ytimg.com/vi/<id>/hqdefault.jpg`. Violação de `UNIQUE` (corrida, ou outro `url` com o mesmo id) vira o mesmo 409, nunca 500 | v1, `erros-v1.md`, Fase 1 |
| F5-11 | **`GET /feed/subscriptions`**: vídeos dos canais com `follow` do dev, mesma ordem e paginação do trending (P-3) | original; sem oráculo v1 (D-5) |
| F5-12 | **Rate limiting de escrita** (ADR 0011: "escrita sensível"): limiter `writes`, 30 por minuto por dev, só em `POST /devs`, `POST /channels` e `POST /video`; 429 no formato do contrato. Reações e leituras não são limitadas | ADR 0011 |
| F5-13 | **`GithubClient` passa para `Shared\Github`** (interface, perfil, cliente HTTP, `GithubUnavailable`) e ganha `publicProfile(login)`; a criação do dev vira a Action `Dev\Actions\EnsureDev`, usada por `Auth`, `Dev` e `Channel`. **Uma feature usa outra só por `Actions` e `Data`**: a regra do teste de arquitetura passa a barrar `Queries`, `Models`, `Http`, `Integrations` e `Support` de outra feature | `arquitetura-alvo.md` |
| F5-15 | **RBAC mínimo, pedido do usuário (2026-10-04)**: coluna `devs.role` (ENUM nativo `dev_role`: `USER`, `ADMIN`; padrão `USER`), migration própria, **sem mexer em canais nem vídeos**. `ADMIN` se define **direto no banco** (`update devs set role = 'ADMIN' where username = '...'`); não há endpoint para isso. **`POST /channels` e `POST /video` exigem `ADMIN`**: `USER` recebe 403 `{"error":"Forbidden."}`, antes da validação, da transação e do limiter (não grava, não chama o GitHub e não gasta o limite). O papel é lido do banco a cada requisição (na mesma query do `auth`), então promover ou rebaixar vale na requisição seguinte com o mesmo token; **não vai no JWT nem no JSON** (o contrato do Dev não muda). Reações, `POST /devs`, listas e assinaturas seguem para qualquer dev autenticado. Devs criados por login, `POST /devs` ou `userGithub` nascem `USER` | Fecha o risco de F5-1 e F5-2 para quem é `USER`. Divergência D-15 |
| F5-14 | **Unicidade e corrida**: tag por `insertOrIgnore` + leitura; canal e vídeo por dedup de aplicação e, como rede, `UniqueConstraintViolationException` → erro tipado (canal: 409 `{"error":"Channel already exists."}`; vídeo: 409 do contrato) | Fase 1 |

## Estrutura

```
app/Shared/Github/{GithubClient, GithubProfile, HttpGithubClient}   app/Shared/Exceptions/GithubUnavailable
app/Shared/Auth/{AuthenticatedDev, DevRole}   database/migrations/2026_10_04_100000_add_role_to_devs_table
app/Features/Auth/{Http/Middleware/RequireAdmin, Exceptions/Forbidden}
app/Features/Dev/{Actions/{EnsureDev, StoreDev, ShowProfile, ReactToDev, ListReactedDevs}, Data, Exceptions/{DevNotFound, GithubUserNotFound},
                  Queries/{DevQueries, DevWriter}, Http/{Controllers, Requests}}
app/Features/Channel/{Actions/{StoreChannel, ReactToChannel}, Data/StoreChannelData, Exceptions/{ChannelNotFound, ChannelAlreadyExists},
                      Queries/ChannelWriter, Http/{Controllers, Requests}}
app/Features/Video/{Actions/StoreVideo, Data/StoreVideoData, Exceptions/{ChannelNotFoundForVideo, VideoAlreadyExists},
                    Support/YoutubeUrl, Queries/VideoQueries, Http/{Controllers, Requests}}
```

Rotas de reação: um controller de adicionar e um de remover por entidade, com o tipo em `->defaults('type', 'like')` na rota.

## Testes (spec → teste → implementação)

| Camada | O que prova |
|---|---|
| Feature, reações | 4 tipos × `POST`/`DELETE` idempotentes (repetir não duplica nem erra), independência like × dislike, auto-like no-op, alvo inexistente ou apagado (400 exato), sem token (401), token de fantasma (401), resposta é o dev do token com as reações atualizadas, listas sem o próprio e sem apagados |
| Feature, `POST /devs` | novo (GitHub falso, minúsculas), existente sem chamar o GitHub, formato inválido (`../x`, espaço, 40 caracteres) 422, GitHub 404 → 404, 500 e falha de rede → 502, corrida (duas inserções, um registro) |
| Feature, `POST /channels` | criar 201, atualizar 200 com tags substituídas, "contém" (F5-1, documentado), emoji removido sem cortar espaço, tags sem repetir, `userGithub` cria o dev só na criação, GitHub 404 ou fora do ar não derruba (201), transação desfeita se uma etapa falha, 422, sem token |
| Feature, `POST /video` | 201 com thumbnail gerada e `&pp=` removido, 400 com o corpo completo, 409 com o vídeo, 409 por `youtube_id` repetido com outro `url`, 422 sem `v=`, canal por link alternativo, sem token |
| Feature, subscriptions | 401; dev01 (segue Alpha) vê 20; sem follow vê 0; seguir Beta soma 35; ordem e página 2 |
| Orçamento de queries | cada operação dentro do orçamento da tabela, com página cheia e parcial |
| Rate limiting | o 31º `POST` de escrita em um minuto dá 429; reações e leituras não |
| Contrato (Gesso) | cada operação contra o OpenAPI (201, 200, 400, 409) |
| RBAC (F5-15) | `USER` 403 em canal e vídeo (inclusive com corpo inválido e sem gravar), `ADMIN` passa, 401 antes do 403, promover e rebaixar vale com o mesmo token, papel fora do JSON, ENUM rejeita valor inválido, 403 não gasta o limiter, reações e `POST /devs` seguem abertos |
| Arquitetura | regra nova de feature × feature; controller sem Models |

## Parte automática × manual

Tudo roda no CI com `GithubClient` falso. As escritas **nunca** rodam contra o deploy do Render do v1. O G3 de escrita usa o v1 **local** e o
`php-laravel` local, recriando os dois bancos antes da rodada (mesma ordem de execução); os casos com `userGithub` e `octocat` usam o GitHub real.
No deploy real o aceite roda com o dataset de paridade semeado e **cria dados no Neon de produção** (precisa da autorização do usuário).

## Fora do escopo

`POST /video/refresh` (Fase 6); `POST /channels/refresh` (fora do escopo, A1); apagar canal, vídeo ou dev (o contrato não tem); Policy de dono do canal (F5-2).

## Critério de aceite

- [x] Testes (412), Pint, Larastan nível 9 e CI em container limpo verdes; orçamentos e 429 provados (`execucao-fase-5.log`, seção 1). [ ] Falta ver o workflow verde no PR.
- [x] Casos de `devs.http`, `channels.http`, `videos.http` e `reactions.http` passam **local** (seção 5). [ ] **No deploy real**: pendente (cria dados no Neon de produção; precisa de autorização e de token de login real).
- [x] G3 de escrita nulo (v1 local × `php-laravel` local): 38 capturas (seção 4); `GET /feed/subscriptions` validado contra o contrato e nos testes (D-5).
- [ ] Divergência D-14 (F5-7 a F5-10, F5-12) aprovada (**adiada pelo usuário em 2026-10-04**). [x] D-15 (RBAC, F5-15) aprovada por pedido do usuário.
- [ ] PR mergeado.
