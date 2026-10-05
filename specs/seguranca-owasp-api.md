# Segurança da API — checklist OWASP API Security Top 10 (2023)

> Fase 7.4, 2026-10-05. Para cada item: o que a API faz, **onde está a prova** (teste, gate do CI ou medida no deploy real) e o **limite conhecido**. Nada aqui é
> "acho que está seguro": o que não tem prova está escrito como limite.

| # | Risco | O que a API faz | Prova | Limite conhecido |
|---|---|---|---|---|
| **API1** | Autorização quebrada em nível de objeto | Toda escrita age sobre o **dev do token** (`AuthenticatedDev`), nunca sobre um id vindo do cliente: reação, `/me`, listas e assinaturas. Alvo inexistente ou apagado dá 400 | `DevReactionsTest` ("a reação grava para o dono do token, nunca para outro"), `BearerAuthTest` ("cada token vê só o seu próprio perfil"), `Read/*` (apagado nunca aparece) | As leituras são públicas por desenho do contrato. `GET /feed/trending?user=` identifica um dev **sem token** (paridade com o original) e mostra o feed personalizado dele: só dado público, mas qualquer um vê |
| **API2** | Autenticação quebrada | OAuth com `state` ligado ao navegador (cookie `HttpOnly`); JWT HS256 com algoritmo fixo, `exp` obrigatório e segredo de no mínimo 32 bytes; cookie `HttpOnly` + `SameSite=Lax`; rate limit do login (10 por minuto por IP); token nunca na query string | `GithubLoginTest` (state ausente, diferente, repetido), `BearerAuthTest` (16 tipos de token inválido, incluindo `alg: none` e segredo errado), `SessionCookieTest`, `WriteBudgetTest` (429) | JWT de 7 dias **sem refresh nem revogação** (trocar o segredo derruba todas as sessões). Limite do login é aproximado sob concorrência (S7) |
| **API3** | Autorização quebrada em nível de propriedade | `JsonResource` e `toContract()` listam os campos um a um: `role` e colunas internas nunca saem. Escrita só por `FormRequest::validated()` e `DB::table` com colunas explícitas (sem mass assignment) | `RoleTest` ("o papel não aparece no JSON"), contrato Gesso em todas as rotas | O `Channel` Eloquent tem `$guarded = []`, mas só é usado para **leitura** |
| **API4** | Consumo irrestrito de recursos | Página fixa em 30 e `page` nunca vira SQL; `/search` com `q` de 1 a 100 caracteres e 10 canais + 20 vídeos; `POST /video/refresh` até 200 candidatos; `ResilientHttp` com timeout; orçamento de queries testado; concorrência da conta limitada a 10 | `Read/SearchTest`, `RefreshRouteTest` (422 acima de 200), `QueryBudgetTest`, `WriteBudgetTest` | **`GET /channels` não é paginado** (contrato, como no v1): 186 canais respondem em ~140 ms no servidor e cresce com o catálogo. **Sem WAF** na Function URL: um cliente consegue ocupar as 10 execuções da conta |
| **API5** | Autorização quebrada em nível de função | `ADMIN` só se define no banco; `POST /channels`, `POST /video` e `POST /video/refresh` exigem `ADMIN` (403 antes de validar, gravar ou gastar o limite) | `RoleTest` (12 casos: promover e rebaixar valem com o mesmo token) | `POST /devs` e as reações ficam abertos a qualquer dev autenticado (decisão do usuário) |
| **API6** | Fluxos de negócio sensíveis sem proteção | Login com rate limit e `state`; criação de dev, canal e vídeo com limite de 30 por minuto por dev; ingestão só `ADMIN` ou agendada | `GithubLoginTest`, `WriteBudgetTest`, `RefreshRouteTest` | Qualquer conta do GitHub entra e cria devs por `POST /devs` (30 por minuto): spam de perfis reais do GitHub é possível |
| **API7** | SSRF | Nenhuma URL vinda do cliente é buscada pelo servidor: `url`, `thumbnail` e `channel_url` só são gravados. O login do GitHub que entra na URL da API do GitHub passa por `^[A-Za-z0-9](?:[A-Za-z0-9-]{0,38})$` | `StoreDevTest` (`../../orgs/x`, `/`, espaço, `?` dão 422 e não chegam ao GitHub) | O bin do JSONBin vem de configuração, não do cliente |
| **API8** | Configuração incorreta de segurança | `APP_DEBUG=false` e `APP_ENV=production` conferidos na função viva; CORS por origem explícita (nunca `*`, sem credenciais); erro genérico sem stack trace; segredos só por `bref-ssm:`; allowlist de recursos; `nosniff` e `no-referrer` em toda resposta, sem `X-Powered-By`; `php.ini` com `expose_php=0` e `zend.exception_ignore_args=1`; varredura de segredos no CI | `smoke.sh` (deploy), `SecurityHeadersTest`, `LogHygieneTest`, `scan-secrets.test.cjs`, `allowlist.test.cjs` | **Achados do deploy real em 2026-10-05, corrigidos nesta fase**: faltavam `X-Content-Type-Options` e `Referrer-Policy` e o `X-Powered-By: PHP/8.4.26` revelava a versão. Sem HSTS: o domínio `lambda-url.on.aws` não é nosso. A UI do `/docs` carrega o Swagger de CDN, sem CSP |
| **API9** | Gestão imprópria do inventário | Um stage só (`prod`), uma Function URL; o **gate de paridade de rotas** do CI falha se uma rota não estiver no contrato; contrato servido em `/docs`; inventário da pilha na allowlist | `scripts/ci.sh` (paridade de rotas), `scripts/custo.sh` | A Function URL é pública e sem autenticação de borda (ADR 0004 segue `proposta`) |
| **API10** | Consumo inseguro de APIs | As respostas do GitHub e do JSONBin são validadas (`login` obrigatório, `record` lista, `access_token` ou `error`); timeout, retry com backoff e `Retry-After`; falha vira 502 ou exit 1 sem repetir a mensagem de baixo nível; chaves só em cabeçalho | `GithubLoginTest` (6 falhas do GitHub), `ResilientHttpTest`, `RefreshCommandTest` | O JSONBin é confiado como fonte do catálogo: um bin adulterado entra por `IngestVideos`, que valida formato e tamanho mas não o conteúdo editorial |

## Verificações transversais

| Item | Resultado |
|---|---|
| `composer audit` (CI a cada PR) | sem vulnerabilidade |
| Varredura de segredos nos arquivos versionados (`scripts/scan-secrets.cjs`, job `tools`) | OK em 318 arquivos; reprova chave AWS, token do GitHub, chave privada, JWT, URL de banco com senha, chave do JSONBin, `.env`, `*.private.*`, dump real e arquivo com sufixo `__` |
| Logs do deploy, 7 dias, 1.894 linhas (`web` e `refresh`) | **0** ocorrências de JWT, `Bearer`, URL de banco, cookie, cabeçalho de autorização, chave, e-mail, stack trace ou `trace` |
| Retenção dos logs | 7 dias nos dois log groups |
| Menor privilégio | role de deploy limitada ao prefixo `devfinder-laravel-prod` (`specs/iam/deploy-role.json`); confiança só no repositório e na `main` (`sub` imutável) |
| Backup e recuperação | **não verificado nesta fase**: o PITR do Neon gratuito é curto e não foi testado |

## O que falta (honesto)

1. WAF ou limite de borda na Function URL: indisponível na conta; só o limite da aplicação e o teto de concorrência 10.
2. Revogação de token e refresh.
3. Teste de restauração do banco.
4. CSP e SRI no `/docs`.
5. e2e **autenticado** contra o deploy real: depende do login real pelo GitHub.
