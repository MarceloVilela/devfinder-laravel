# Auditoria do original (`devfinder-api`) e do v1 (`php-codei`)

> Leitura do **conteúdo**, não só da forma (lição do v1: dois bugs só apareceram assim). Data:
> 2026-10-02. Escopo: rotas do OpenAPI. Cada achado tem decisão proposta; as marcadas **(usuário)**
> esperam aprovação. Não foi rodado nada contra o original (Express + Mongo); o que veio do v1 está
> marcado.

## Achados

| # | Achado | Onde | Decisão proposta |
|---|---|---|---|
| A1 | **O v1 não implementa 3 das 30 operações** (`GET /search`, `GET /feed/subscriptions`, `POST /channels/refresh`): o Render responde 404 para as duas primeiras (testado em 2026-10-02) e `Routes.php` não as declara. O `specs/README.md` do v1 diz "cobertura das 30 operações". | `php-codei/app/Config/Routes.php` | O oráculo cobre 27 operações. Para as 3 restantes o oráculo é o `devfinder-api` e o contrato. **Decidido (2026-10-02)**: `search` (Fase 3) e `subscriptions` (Fase 5) **entram**; `channels/refresh` **fica fora** (canal já estava fora da Fase 6). A operação fora continua no OpenAPI, marcada como não implementada |
| A2 | **Regex injection / ReDoS em `/search`**: `new RegExp(String(q), 'i')` sem escapar; sem `q`, busca por `"undefined"`; sem limite e sem paginação (varre as coleções inteiras). | `devfinder-api/src/controllers/Search/SearchController.ts` | Termo parametrizado (`ILIKE` com escape de `%` e `_`, ou full-text), `q` obrigatório (422), limite de resultados. Divergência aprovada |
| A3 | **`throw` dentro de handler `async` do Express 4** (`user ... not found`): sem tratamento, a requisição pendura ou cai no handler padrão. | `SubscriptionsController`, `DevController` | **Decidido**: token válido de Dev inexistente em rota obrigatória → 401 `{"error":"Token invalid."}`; em rota opcional → segue anônimo (mesmo comportamento do v1). Nunca exceção solta |
| A4 | **Autenticação por cookie `httpOnly` é o caminho real** do frontend; o Bearer é só fallback. O v1 só aceita Bearer, e o payload do JWT mudou de `{id}` para `{username}`. | `devfinder-api/src/middlewares/auth.ts` | **Decidido**: só **Bearer** com payload `{username}` (como o v1); cookie `httpOnly` **fora do escopo**. Sem credenciais em CORS. Entra como limitação conhecida no README (o frontend original usa cookie) |
| A5 | **CORS**: o original usa `cors({origin: APP_WEB_URL, credentials: true})`. No v1 o filtro `cors` existe em `Filters.php` mas **não está ativo** em nenhum grupo. | `devfinder-api/src/server.ts`; `php-codei/app/Config/Filters.php` | CORS com origem explícita por variável de ambiente, nunca `*` |
| A6 | **Sem validação de entrada no v1**: os controllers indexam `$body['title']`, `['url']`... direto. | `VideoController::store` etc. | Form Requests (ver `erros-v1.md`, ponto 1) |
| A7 | **Chamada ao GitHub dentro do controller**, sem timeout nem tratamento de falha de rede. | `ChannelController::store` | Action + interface `GithubClient` com timeout e retry |
| A8 | **N+1**: `GET /devs` faz 126 queries para 30 itens (`DevPresenter::present` roda 4 por Dev). | v1, `specs/README.md` | Orçamento de queries por endpoint (Fase 1) |
| A9 | **`GET /video/{id}` inexistente devolve 200 + `null`**. | v1 e original | Preservar por paridade; registrar no contrato |
| A10 | **`GET /description/category` serializa um array como string** (`res.send(array)`). Sem decisão em nenhum dos projetos. | `serverless/specs`, `fase-0-especificacao.md` do v1 | **Decidido**: **preservar** a serialização atual por paridade e documentar no contrato |
| A11 | **Bugs já corrigidos no v1** (reaproveitar o teste, não o código): `POST /channels` criava `Dev` sem `username`; o OpenAPI omitia `userGithub`/`avatar`. | `../php-codei/specs/fase-5-escrita-relacionamentos.md` | Teste que falha antes do conserto na Fase 5 |
| A13 | **`POST /devs` com username inexistente no GitHub devolve 500** no v1 (o `.http` do v1 o declara "limitação aceita"; confirmado no baseline). | `acceptance/execucao-v1-baseline.log` | **Divergência D-6 aprovada (2026-10-02)**: erro tipado, 404 (username inexistente no GitHub) ou 502 (GitHub fora ou timeout), nunca 500 |
| A14 | **Ids são inteiros no v1, string no contrato.** `_id`, `channel_id` e os ids dentro de `likes`/`deslikes`/`follow`/`ignore` vêm como inteiro (autoincremento do MySQL); o OpenAPI e o frontend (`devfinder-next/src/types`: `_id: string`) tratam como string (o original usa ObjectId do Mongo). Medido em 20 respostas (`acceptance/validacao-contrato-v1.log`). | `validacao-contrato-v1.log` | **Divergência D-7 aprovada (2026-10-02)**: o `php-laravel` devolve **string** (cast no API Resource), cumprindo o contrato e o tipo do frontend; o contrato não muda |
| A15 | **`null` onde o contrato não declara `nullable`**: `userGithub`, `description`, `avatar` (canal) e `viewnum`, `date` (vídeo). O original (Mongo) provavelmente omitia o campo. | `validacao-contrato-v1.log` | **Divergência D-8 aprovada e aplicada (2026-10-02)**: `null` continua (paridade com o v1) e o contrato agora declara `nullable: true` nesses 5 campos |
| A12 | **Ambiente `real` do v1 grava no banco de produção** quando os `.http` de escrita rodam contra ele. | `../php-codei/specs/README.md` | O G3 usa o v1 **local** (Docker) com o mesmo dataset; nunca roda escrita contra o Render |

## Registro de divergências aprovadas

Toda diferença deliberada entre o v1/original e o `php-laravel` entra aqui **antes** de ser implementada. O G3
compara só o que **não** está nesta tabela.

| # | Divergência | Motivo | Aprovada por | Data |
|---|---|---|---|---|
| D-1 | Busca segura: termo parametrizado (`ILIKE` com escape ou full-text), `q` obrigatório (422), limite de resultados | A2 | usuário | 2026-10-02 |
| D-2 | Token válido de Dev inexistente: 401 em rota obrigatória, anônimo em rota opcional | A3 | usuário | 2026-10-02 |
| D-3 | Validação de entrada com Form Request: 422 `{"error":"...","errors":{campo:[...]}}` (v1 e original não validam) | A6 | usuário | 2026-10-02 |
| D-4 | CORS com origem explícita por variável de ambiente, nunca `*`, sem credenciais | A5 | usuário | 2026-10-02 |
| D-5 | `GET /feed/subscriptions`, `GET /search`: implementados sem oráculo v1; resposta definida pelo contrato e pelo original | A1 | usuário | 2026-10-02 |
| D-6 | `POST /devs` com username inexistente no GitHub: 404 `{"error":"..."}`; falha ou timeout do GitHub: 502; nunca 500 (o v1 devolve 500) | A13 | usuário | 2026-10-02 |
| D-7 | Ids expostos como **string** (`_id`, `channel_id` e ids em `likes`/`deslikes`/`follow`/`ignore`); o v1 devolve inteiro. O G3 normaliza o id do v1 para string antes de comparar | A14 | usuário | 2026-10-02 |
| D-8 | Campos `userGithub`, `description`, `avatar` (canal) e `viewnum`, `date` (vídeo) continuam podendo ser `null` (como no v1); o contrato passou a declará-los `nullable: true` | A15 | usuário | 2026-10-02 |
| D-9 | Exemplos do OpenAPI trocados por dados sintéticos (`Canal Alpha`, `dev01`, `vidalpha01`, `example.test`), sem nomes nem ids reais de terceiros; só os `example:`, nenhum schema mudou | regra de não versionar dados de terceiros | usuário | 2026-10-02 |
| D-10 | Ids do JSON são **UUIDv7 em string** (era inteiro no v1 e ObjectId no original); só o formato do valor muda, o tipo no contrato segue `string` (D-7) | Fase 1, P-2 | usuário | 2026-10-03 |
| D-11 | Respostas paginadas ganham `page` e `totalPages` (aditivo): `docs`, `total` e `itemsPerPage` ficam idênticos; `page` informa a página realmente servida (resolve a ambiguidade do clamp) | Fase 1, P-3 | usuário | 2026-10-03 |
| D-12 | Falha do GitHub no callback: erro do usuário (sem `code`, `state` ausente ou diferente, `code` recusado) volta ao front sem token (`302 ${APP_WEB_URL}/login`); GitHub fora do ar vira 502 `{"error":"GitHub unavailable."}`; nunca 500 (o v1 devolvia 500). | A7, A13 | usuário | 2026-10-04 |
| D-13 | OAuth com `state` aleatório ligado ao navegador por cookie `HttpOnly` (o v1 e o original não tinham `state`: login CSRF). | plano, Fase 4 | usuário | 2026-10-04 |

Aprovação de D-12 e D-13 dada pelo usuário em 2026-10-04 (Fase 4), com a condição de que o efeito para o front seja nenhum (o fluxo no navegador é o mesmo). Aprovação de D-10 e D-11 dada pelo usuário em 2026-10-03 (Fase 1, P-2 e P-3; confirmada após conferir o frontend: ids opacos, campos extras ignorados). Aprovação de D-1 a D-9 dada pelo usuário em 2026-10-02 (D-1 a D-5 em bloco, "aceito as sugestões"; D-6 a D-9 uma a uma). Divergência nova só entra aqui **antes** de ser implementada.
