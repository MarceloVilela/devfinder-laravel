# Fase 4 — Autenticação (GitHub OAuth + JWT)

> Referência: [`../plan.md`](../plan.md), seção 7, Fase 4. Branch `fase-4-autenticacao`, a partir de `main` (PR #7 mergeado).
> Status: **implementada localmente; aguardando o OAuth App do usuário, os parâmetros do SSM, o login real e o PR** (iniciada e implementada em 2026-10-04). Spec antes do código; o aceite se prova em `execucao-fase-4.log`.
> Contrato: [`fase-0-openapi.yaml`](./fase-0-openapi.yaml). Casos: `acceptance/auth.http`. ADRs: [`0011`](./adr/0011-rate-limiting-store.md) (store do limiter), [`0013`](./adr/0013-oauth-direto-e-jwt.md) (OAuth direto e JWT).

## Escopo

| # | Operação | Estratégia |
|---|---|---|
| 1 | `GET /auth/github` | Action `BeginGithubLogin`; redirect 302 com `state` e cookie |
| 2 | `GET /auth/github/callback` | Action `CompleteGithubLogin`; redirect 302 para o front com o token |
| 3 | `GET /me` | middleware `auth` + `Queries` (Dev feature) |
| — | Middleware `auth` (obrigatório) e `auth.optional` | JWT Bearer; o optional entra em todas as rotas `x-auth: optional` |
| — | **Personalização** adiada pela Fase 3 (F3-1) | `GET /devs` e `GET /feed/trending` |
| — | Rate limiting das rotas de login | store `database` (ADR 0011), teste do 429 |

## Decisões desta fase

| # | Decisão | Motivo |
|---|---|---|
| F4-1 | **OAuth direto**, sem Socialite: `GithubClient` (interface) + `HttpGithubClient` (`Http` do Laravel, timeout 5 s, 1 retry só em erro de conexão ou 5xx); ligado no `AppServiceProvider`; testes com `Http::fake` | Paridade com o v1 (mesmo mecanismo), superfície menor, testável; ADR 0013 |
| F4-2 | **`state` anti-CSRF ligado ao navegador**: `GET /auth/github` gera 32 bytes aleatórios, grava num cookie `devfinder_oauth_state` (`HttpOnly`, `SameSite=Lax`, `Path=/v1/auth`, 10 min, `Secure` em produção) e manda o mesmo valor em `state`. O callback exige `hash_equals(cookie, state)` e apaga o cookie. Sem armazenamento no servidor. O v1 não tinha `state` (login CSRF) | Plano; stateless no Lambda |
| F4-3 | **Erros do callback**: sem `code`, `state` ausente ou diferente, ou GitHub recusando o `code` (`bad_verification_code`, `access_denied`) → **302 para `${APP_WEB_URL}/login`**, sem token (é fluxo de navegador; o v1 já fazia isso sem `code`). GitHub **fora do ar** (timeout, 5xx, falha de rede, perfil sem `login`) → **502** `{"error":"GitHub unavailable."}`, causa só no log. Nunca 500 | O v1 devolvia 500 (A13/D-6); divergência D-12 |
| F4-4 | **JWT** HS256 (`firebase/php-jwt`), claims `username`, `iat`, `exp` (7 dias, como o contrato); algoritmo fixado na verificação; `exp` obrigatório; segredo `JWT_SECRET` com **no mínimo 32 bytes** (senão recusa assinar e verificar). Segredo no SSM (`bref-ssm:`) | Contrato (`bearerAuth`), plano |
| F4-5 | ~~**Entrega do token**: `302 ${APP_WEB_URL}/login?token=<jwt>`~~ **Substituída pela F4-13 (2026-10-04)**: o token não vai mais na URL | Mitigava o vazamento por referer e histórico; o cookie o elimina |
| F4-6 | **Middleware obrigatório**: sem cabeçalho → 401 `{"error":"Token not provided."}`; esquema diferente de `Bearer`, token adulterado, expirado, sem `username`, de segredo errado, ou de Dev inexistente → 401 `{"error":"Token invalid."}` (D-2). **Opcional**: qualquer um desses casos segue anônimo, nunca 401 | `erros-v1.md`, D-2 |
| F4-7 | **Dev do token** carregado por `norm_text(username)` em **1 query** (soft delete respeitado) e entregue como `AuthenticatedDev` (em `Shared`, para o `PageRequest` e as features lerem sem importar `Auth`) | Orçamento: `/me` 3 queries, `GET /devs` 4 + 1 |
| F4-8 | **Upsert do Dev** no callback: `username` em minúsculas (como o v1), `name` cai para o login, `bio` vazia, `avatar` do perfil; `insertOrIgnore` + `select` (corrida vira um registro só, Fase 1) | Fase 1, v1 |
| F4-9 | **Personalização**: `GET /devs` autenticado exclui o próprio dev e quem já recebeu `like` ou `dislike` (só esses dois tipos, como no v1) na contagem **e** na página; `GET /feed/trending` autenticado, ou com `?user=<username>` (sem token, como no v1 e no original), exclui vídeos de canais em `ignore`. O token vale mais que `?user=`; `?user=` desconhecido segue anônimo | `acceptance/*.http`, `fase-1-modelo-de-dados.md` |
| F4-10 | **Rate limiting**: limiter `auth`, 10 por minuto por IP, só em `/auth/github` e `/auth/github/callback`, store `database` (`CACHE_LIMITER_STORE`), 429 `{"error":"Too many requests."}` com `Retry-After`. `/me` e as leituras não são limitadas (cada requisição limitada acorda o Neon, S7) | ADR 0011 |
| F4-11 | **`GITHUB_REDIRECT_URI` opcional**: se definida vai em `redirect_uri`; se não, o GitHub usa a URL cadastrada no OAuth App (um App por ambiente) | Evita depender de `Host`/`X-Forwarded-Proto` do Lambda |
| F4-13 | **Sessão por cookie `httpOnly`, para o `devfinder-next` atual (pedido do usuário, 2026-10-04)**: o callback abre a sessão com `Set-Cookie: devfinder_token=<jwt>` (`HttpOnly`, `SameSite=Lax`, `Path=/`, 7 dias, `Secure` em produção) e redireciona a `${APP_WEB_URL}/login` **sem token na URL**; o front descobre a sessão com `GET /me`. `POST /auth/logout` limpa o cookie (204, idempotente, sem exigir sessão). O middleware lê o token do **cookie primeiro** e depois de `Authorization: Bearer` (mesma ordem do `devfinder-api`); Bearer segue para Swagger UI e servidor a servidor; nunca da query string. Nome do cookie `devfinder_token`, como o original. `SameSite=Lax` e CORS sem credenciais: o navegador só chega à API pelo proxy `/backend` do front (mesmo site), então não há requisição cross-site com cookie e a sessão não abre brecha de CSRF | Reverte a decisão A4 de 2026-10-02 ("só Bearer"), porque o front v4 só tem sessão por cookie. Divergência D-16 |
| F4-12 | Comando `auth:mint-token {username}` só fora de produção, para gerar token sintético nos `.http` e na prova local; recusa em `APP_ENV=production` | Os `.http` pedem token de `dev01` e de um fantasma |

## Configuração nova

| Variável | Onde | Produção |
|---|---|---|
| `JWT_SECRET` | `config/devfinder.php` | SSM `/devfinder-laravel/prod/jwt-secret` (SecureString) |
| `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET` | idem | SSM `.../github-client-id` e `.../github-client-secret` |
| `APP_WEB_URL` | idem | variável do GitHub (`--param webUrl`), como o CORS |
| `GITHUB_REDIRECT_URI` | idem | opcional |
| `CACHE_LIMITER_STORE` | `config/cache.php` (`limiter`) | `database` |

`JWT_SECRET`, `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET` e `APP_WEB_URL` passam a ser **obrigatórios em produção** no `config:check`. Os parâmetros do SSM precisam existir **antes** do deploy desta fase (criados em 2026-10-04: `jwt-secret`, `github-client-id`, `github-client-secret`; variável `APP_WEB_URL`; `ssm:GetParameters` na role de deploy): o `bref-ssm:` de um parâmetro inexistente derruba o cold start.

## Estrutura

```
app/Shared/Auth/AuthenticatedDev            identidade lida por PageRequest e features
app/Features/Auth/{Actions, Data, Exceptions, Integrations/{GithubClient,HttpGithubClient},
                   Http/{Controllers,Middleware}, Queries/{DevLookup,DevProvisioner}, Support/TokenCodec}
app/Features/Dev/Http/Controllers/ShowMeController (+ DevQueries::view)
```

## Testes (spec → teste → implementação)

| Camada | O que prova |
|---|---|
| Feature, fluxo OAuth com `Http::fake` | redirect com `client_id`, `state`, sem `scope`; cookie com os atributos; callback feliz cria Dev e redireciona com token válido; Dev existente é reaproveitado (sem duplicar); `state` ausente, diferente, sem cookie, repetido; sem `code`; GitHub recusando `code`; GitHub 500, timeout, perfil sem `login` → 502 sem vazar |
| Feature, middleware | sem token, esquema errado, malformado, adulterado (payload trocado, assinatura trocada, `alg: none`), expirado, segredo errado, Dev inexistente, Dev apagado, token de outro usuário (`/me` devolve o dono do token) |
| Feature, personalização | `GET /devs` com `dev01` (total 32: sai ele, `dev02` e `dev03`) e sem reação (total 34); token inválido em rota opcional não bloqueia; `feed/trending` por token e por `?user=` (20) e `?user` desconhecido (55); orçamento de queries (+1) |
| Rate limiting | o 11º pedido a `/auth/github` dá 429 com `Retry-After`; `/me` e `/devs` não são limitados |
| Cookie de sessão (F4-13) | cookie autentica `/me` e rotas autenticadas e personaliza as opcionais; cookie vale antes do Bearer; cookie inválido, expirado, de fantasma ou de dev apagado: 401 ou anônimo; atributos (`HttpOnly`, `SameSite=Lax`, validade, `Secure`); logout 204 limpa com os mesmos atributos; nunca da query string; CORS sem credenciais |
| Contrato (Gesso) | `GET /me` 200 e os redirects contra o OpenAPI |
| Unidade | `TokenCodec` (assina, verifica, segredo curto, `exp`); montagem da URL de autorização |
| Arquitetura | `Auth` não importa outra feature; controller sem `Http`/`DB`; exceções estendem `ApiException` |

## Parte automática × manual

O fluxo roda no CI com `GithubClient` falso. O **login real** é manual (OAuth App do usuário): o usuário registra o App
(Homepage `APP_WEB_URL`, callback `<Function URL>/v1/auth/github/callback`), grava `GITHUB_CLIENT_ID` e
`GITHUB_CLIENT_SECRET` no SSM (sem passar o segredo pelo chat) e abre `/v1/auth/github` no navegador. A evidência versionada é a saída do
`/me` com o token, sem o token.

## Fora do escopo

`POST /devs` e as demais escritas (Fase 5); refresh token. (O cookie `httpOnly` de sessão e o logout, antes fora do escopo, entraram pela F4-13.)

## Critério de aceite

- [x] Testes (259), Pint, Larastan nível 9 e CI em container limpo verdes (`execucao-fase-4.log`, seção 1); rate limiting com 429 provado (testes e seção 6). [ ] Falta ver o workflow verde no PR.
- [x] Casos de `auth.http` passam **local** (seção 5). [ ] **No deploy real**: depende dos parâmetros do SSM e do merge (o deploy só passa com eles).
- [ ] **Login real** ponta a ponta com o OAuth App do usuário e `GET /me` devolvendo o Dev.
- [x] G3 nulo em 40 capturas contra o v1 local: as 30 anônimas (regressão da Fase 3) e `/me`, `/devs` e `/feed/trending` personalizados, mais os 401 (seção 4).
- [x] ADR 0013 aceita pelo usuário (2026-10-04). [x] Divergências D-12 e D-13 aprovadas (2026-10-04).
- [ ] PR mergeado.
