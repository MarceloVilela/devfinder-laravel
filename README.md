# DevFinder API — Laravel

<div flex-direction="row">
  <img src="https://img.shields.io/static/v1?style=for-the-badge&logo=PHP&label=php&message=8.4&color=success" />
  <img src="https://img.shields.io/static/v1?style=for-the-badge&logo=Laravel&label=laravel&message=13&color=success" />
  <img src="https://img.shields.io/static/v1?style=for-the-badge&logo=PostgreSQL&label=postgresql&message=18&color=success" />
  <img src="https://img.shields.io/static/v1?style=for-the-badge&logo=AmazonAWS&label=aws%20lambda&message=bref&color=success" />
</div>

<br />
<strong>Conteúdos sobre Tecnologia, Desenvolvimento e Programação em Português.</strong>
<br />

## Sobre o projeto

**DevFinder** cataloga canais e vídeos brasileiros de tecnologia no YouTube. Devs entram com o GitHub, curtem ou
descurtem outros devs e seguem ou ignoram canais para personalizar o feed de vídeos (em alta, por canal ou só dos
canais que seguem). Há busca unificada de vídeo e canal e textos prontos para descrição de vídeo, agrupados por feed
ou por categoria.

Este repositório é a **versão em PHP/Laravel da API**: mesmo domínio e **mesmo contrato público** do
[`devfinder-api`](https://github.com/MarceloVilela/devfinder-api) original (Express + MongoDB), consumido pelo
[`devfinder-next`](https://github.com/MarceloVilela/devfinder-next) (o site em [devfinder.vercel.app](https://devfinder.vercel.app)).
O objetivo é mostrar, com evidência, **como eu trabalho com PHP**: arquitetura por feature, testes de contrato e de
arquitetura, banco relacional modelado com cuidado e uma metodologia **spec-driven** em que cada decisão fica escrita,
medida e revisável. Roda em AWS Lambda ([Bref](https://bref.sh)) com PostgreSQL no [Neon](https://neon.tech), a custo zero.

## Mesmo produto, quatro implementações

| Repositório | Stack | O que demonstra |
|---|---|---|
| [`devfinder-api`](https://github.com/MarceloVilela/devfinder-api) | Node, Express, MongoDB | A API original e a fonte do contrato |
| [`devfinder-serverless`](https://github.com/MarceloVilela/devfinder-serverless) | Node/TypeScript, API Gateway, Lambda, DynamoDB | Migração para AWS serverless, *single-table design*, ingestão agendada |
| [`devfinder-codeigniter`](https://github.com/MarceloVilela/devfinder-codeigniter) | PHP 8.3, CodeIgniter 4, MySQL | MVC clássico em PHP, deploy em container (Render + TiDB Cloud) |
| **`devfinder-laravel`** (este) | PHP 8.4, Laravel 13, PostgreSQL, Lambda + Bref | Laravel moderno, arquitetura por feature, serverless com custo zero |

A versão CodeIgniter funciona aqui como **oráculo de paridade**: as mesmas requisições, sobre o mesmo dataset, precisam
dar a mesma resposta nas duas (ver [G3](#como-foi-especificado-spec-driven)).

## O que a API faz

| Área | Operações | Comportamento |
|---|---|---|
| **Catálogo público** | `GET /devs`, `/devs/{username}`, `/channels`, `/channels/{q}`, `/feed/trending`, `/feed/channel`, `/video/{id}` | Paginação de 30, página inválida vira 1 e página além do fim serve a última; canal achado por nome ou link, sem caixa e sem acento; inexistente responde `200 null`, como o original |
| **Busca e descrições** | `GET /search`, `/description/feed`, `/description/category` | Busca parametrizada (nunca regex do usuário) por canal e vídeo; texto compartilhável com hashtags derivadas das tags |
| **Conta** | `GET /auth/github`, `/auth/github/callback`, `GET /me`, `POST /auth/logout` | GitHub OAuth com `state` anti-CSRF; sessão por **cookie `httpOnly`** (como o original, é o que o `devfinder-next` usa) ou `Authorization: Bearer`; JWT de 7 dias, sem token na URL |
| **Personalização** | os mesmos `GET`, com token | `/devs` sem quem você já curtiu ou descurtiu; `/feed/trending` sem os canais ignorados; `GET /feed/subscriptions` com os vídeos dos canais que segue |
| **Relacionamentos** | `POST`/`DELETE` em `/likes` e `/dislikes` de devs e de canais, `GET /likes/devs`, `/dislikes/devs` | Like, dislike, follow e ignore idempotentes e independentes entre si |
| **Cadastro** | `POST /devs`, `/channels`, `/video` | `POST /devs` consulta o GitHub; canal e vídeo só para o papel `ADMIN` |
| **Documentação** | `GET /docs` | Swagger UI servida a partir do próprio contrato (`specs/fase-0-openapi.yaml`) |

**28 das 30 operações do contrato estão implementadas.** Falta `POST /video/refresh` (ingestão em lote agendada, Fase 6);
`POST /channels/refresh` ficou fora do escopo por decisão registrada.

## Estado

| Fase | Entrega | Situação |
|---|---|---|
| 0 | Especificação, 13 ADRs, oráculo, spikes S1 a S3 | concluída |
| 1 | Modelo de dados PostgreSQL e orçamento de queries por operação | concluída |
| 2a / 2b | Esqueleto local, CI, deploy real (Bref + Neon + OIDC), guardrails de custo | concluídas |
| 3 | Leitura pública (10 rotas) | concluída, com aceite no deploy real |
| 4 | Autenticação GitHub OAuth + JWT, sessão por cookie | mergeada; o cookie e o logout estão na Fase 5; falta o login real ponta a ponta |
| 5 | Escrita, relacionamentos e papéis | implementada, em revisão |
| 6 a 8 | Ingestão agendada; observabilidade e desempenho; fechamento | pendentes |

## Como foi especificado (spec-driven)

O projeto inteiro é **spec antes do código**, um PR por fase, e nenhuma fase avança sem a evidência da anterior.

**Fluxo de cada fase**: spec escrita e aprovada → testes → implementação → evidência versionada em
`specs/execucao-fase-N.log` (saída real de comandos, nunca prosa) → PR. A fase seguinte só começa com o PR anterior mergeado.

**Gates**: **G0** viabilidade (spikes medidos), **G1** latência, **G2** custo (US$ 0 e nenhum recurso fora da allowlist),
**G3** paridade com o oráculo e **G4** arquitetura (teste de arquitetura verde).

### O que cada artefato responde

| Artefato | Conteúdo |
|---|---|
| [`plan.md`](./plan.md) | Plano completo: decisões D1 a D8 que mudam o v1, fases, gates, definição de pronto, riscos |
| [`specs/fase-0-openapi.yaml`](./specs/fase-0-openapi.yaml) | **Contrato único** das 30 operações. Divergência se corrige aqui primeiro; o teste de contrato lê este arquivo |
| [`specs/fase-N-*.md`](./specs/README.md) | Spec de cada fase: escopo, decisões numeradas (F3-1, F4-1, F5-1…), testes previstos e critério de aceite |
| [`specs/adr/`](./specs/adr/README.md) | 13 ADRs com contexto, alternativas, custo e **critério de reversão**; só viram `aceita` com a evidência existindo |
| [`specs/spikes/`](./specs/spikes/) | Experimentos medidos: Laravel em Bref, `pdo_pgsql` no Neon, *cold start*, pooler, store do rate limit, ferramentas de contrato e arquitetura |
| [`specs/auditoria-original.md`](./specs/auditoria-original.md) | Achados da leitura do código do original (regex injection, `throw` em handler async, cookie, CORS…) e o **registro de divergências** D-1 a D-15 |
| [`specs/oraculo-v1.md`](./specs/oraculo-v1.md), [`dataset-de-paridade.md`](./specs/dataset-de-paridade.md) | Como usar o v1 como referência e o dataset sintético idêntico nos dois lados |
| [`specs/acceptance/*.http`](./specs/acceptance/README.md) | Um caso de aceite por rota, com cenários de erro, rodando contra `v1`, `local` e `real` |
| [`specs/teardown.md`](./specs/teardown.md), [`specs/iam/`](./specs/iam/) | Plano de desligamento executável e política mínima da role de deploy |

### Decisões que viraram ADR

| ADR | Decisão |
|---|---|
| 0001 a 0003 | Laravel; PostgreSQL no Neon; Lambda + Bref |
| 0004 | Entrada HTTP por Function URL (**ainda `proposta`**: a conta não permite concorrência reservada nem WAF) |
| 0005, 0006 | Organização por feature com uma Action por caso de uso; **sem Repository genérico** (Eloquent e objetos de consulta) |
| 0007, 0008 | Contrato servido e testado a cada PR; erros tipados num único ponto de renderização, formato do contrato preservado |
| 0009, 0010 | PHP 8.4, Laravel 13 e `us-east-2`; custo zero **durante o Free Plan**, com decisão de fim de vida até o dia 150 |
| 0011 | Rate limiting com cache em tabela do Postgres (o cache `array` zera a cada requisição no Lambda: medido no S7) |
| 0012 | Modelo relacional: UUIDv7, `norm_text()` sem caixa e sem acento, índices parciais para *soft delete*, `pg_trgm` na busca, ENUM nativo |
| 0013 | OAuth do GitHub direto (sem Socialite) e JWT próprio, `state` ligado ao navegador por cookie |

### Paridade com o v1 (G3)

O v1 e este projeto sobem o **mesmo dataset sintético** (35 devs, 3 canais, 55 vídeos), recebem as **mesmas requisições** na
mesma ordem e têm as respostas normalizadas (ids viram a chave natural, datas e campos aditivos saem) antes do `diff`.
Estado: **nulo em 78 capturas** (30 de leitura anônima, 10 com token, 38 de escrita). As diferenças deliberadas ficam no registro de
divergências, **aprovadas antes de implementar**:

- `POST /devs` com usuário inexistente no GitHub: 404 (o v1 devolvia 500); entrada validada com 422 (o v1 não validava).
- `GET /search`: termo parametrizado, `q` obrigatório e limitado.
- Ids em UUIDv7 (string), paginação com `page` e `totalPages` aditivos, CORS por origem explícita (nunca `*`).
- Token válido de dev inexistente: 401 em rota obrigatória, anônimo em rota opcional.
- `state` no OAuth e erros do callback sem 500; papéis `USER` e `ADMIN`.

### Rigor em números

- **Orçamento de queries por operação**, definido na spec e testado (ex.: `GET /devs` faz 4 queries para 30 itens; o v1 chegava a 126). O teste conta as queries de cada rota e falha se passar.
- **Medido no deploy real**: quente com banco p95 de 51 ms; Lambda e Neon frios em torno de 2,2 s; Lambda frio sem banco 1,64 s (meta de 1,5 s: **fora por 0,14 s, assumido e registrado** para a Fase 7).
- **Entrada hostil** testada em `/search` e nos corpos de escrita: aspas, `%`, `_`, `(a+)+$`, byte nulo, UTF-8 inválido, login do GitHub com `../`.

### O que a metodologia pegou no caminho

Cada item abaixo está no log da fase correspondente.

- O G3 achou lacuna no próprio dataset (`description` e `avatar` dos canais ficaram de fora da spec).
- `response()->json(null)` devolve `{}`: o `200 null` do contrato precisou de resposta própria, e o teste pegou.
- O OIDC do GitHub falhou no primeiro deploy: o repositório emite `sub` **imutável** (com ids), e a confiança da role usava o formato antigo.
- Contando queries descobri que, no ambiente local, **os testes rodavam no banco de desenvolvimento** (o `.env` injetado pelo docker vence o `force` do PHPUnit; no CI não acontecia). Corrigido, com guarda que falha se o banco não for o de teste.
- O limiter de escrita usava o IP porque o `throttle` é middleware prioritário e rodava antes do `auth`.

## Implementação (resumo)

| | |
|---|---|
| Linguagem e framework | PHP 8.4, Laravel 13 |
| Banco | PostgreSQL 18 no Neon (pooler no runtime, conexão direta só nas migrations do CI) |
| Execução | AWS Lambda com Bref (`php-84-fpm`), Function URL |
| Segredos | SSM Parameter Store, lidos pelo runtime (`bref-ssm:`); nenhum segredo em texto no template |
| Deploy | GitHub Actions com OIDC (sem chave de longa duração), só na `main`, com *guard* dos parâmetros e smoke test |
| Testes e qualidade | Pest (feature, contrato, arquitetura, orçamento de queries), Gesso para contrato, Larastan nível 9 sem baseline, Pint |

```
app/
├── Features/<Feature>/{Actions,Data,Http/{Controllers,Requests,Resources},Queries,Models,Exceptions}
│   Auth · Channel · Description · Dev · Info · Search · Video
└── Shared/{Auth,Exceptions,Github,Http,Pagination,Support}
```

- **Controller** só orquestra e é invocável. **Action** onde há regra; leitura sem regra chama um objeto de `Queries/` direto.
- Entrada por **Form Request** e DTO `readonly`; saída por **API Resource** com o formato exato do contrato.
- **Erros tipados** (`ApiException`) renderizados em um único ponto, sempre no formato do contrato, sem *stack trace*.
- **Uma feature usa outra só por `Actions` e `Data`**, imposto pelo teste de arquitetura.
- Integração externa (GitHub) atrás de interface, trocada por `Http::fake` nos testes.
- Escrita concorrente resolvida no banco: `INSERT … ON CONFLICT DO NOTHING` nas reações e na criação de dev, `UNIQUE` como rede de segurança.

### Gates do CI (todos reproduzíveis em container limpo)

`composer validate --strict`, `composer audit`, Pint, Larastan nível 9, `config:check`, migrate com rollback e migrate,
paridade de rotas com o contrato, **412 testes**, empacotamento do Lambda com **allowlist de recursos AWS** (reprova RDS,
NAT, API Gateway, Secrets Manager, log sem retenção, segredo em texto) e smoke test contra o deploy.

## Rodar localmente

O host não precisa de PHP nem Composer, só Docker:

```sh
cp .env.example .env              # preencher JWT_SECRET (openssl rand -hex 32) e, para o login, o OAuth App do GitHub
docker compose up -d --build      # PostgreSQL 18 + app em http://localhost:8082  (Swagger UI em /docs)
./run.sh php artisan migrate:fresh --seed --seeder='Database\Seeders\ParityDatasetSeeder'
./run.sh vendor/bin/pest          # testes (rodam só no banco devfinder_test)
scripts/reproduz-ci.sh            # o CI inteiro, em container limpo
```

```sh
./run.sh php artisan auth:mint-token dev01     # token sintético (só fora de produção)
docker compose exec -T db psql -U devfinder -d devfinder \
  -c "update devs set role = 'ADMIN' where username = 'dev01'"   # papel ADMIN: direto no banco
```

Os casos de aceite ficam em [`specs/acceptance/`](./specs/acceptance/README.md) (`npx httpyac send *.http --env local --all`). O frontend
([`devfinder-next`](https://github.com/MarceloVilela/devfinder-next)) aponta para a API com `NEXT_PUBLIC_API_URL=http://localhost:8082/v1`.

## Limitações conhecidas

- **Login em produção com o `devfinder-next`**: o cookie de sessão é do host que responde ao callback. Para ele chegar ao domínio do front, o login e o callback precisam passar pelo proxy `/backend` do front (`GITHUB_REDIRECT_URI=https://<front>/backend/auth/github/callback` e o link de login do front apontando para `/backend/auth/github`); com o link direto à API, o `state` e a sessão nascem no domínio da API. Localmente (`localhost`) funciona direto.
- **Papéis (RBAC mínimo)**: `devs.role` é `USER` por padrão e `ADMIN` se define direto no banco (não há endpoint). Só `ADMIN` chama `POST /channels` e `POST /video`; `USER` recebe 403. O papel é lido do banco a cada requisição e não aparece no JSON. Para `ADMIN`, o canal existente continua sendo achado por "contém" no nome ou no link (como no v1): um título curto como "Tech" atualiza o canal cujo nome o contém.
- **Autenticação**: JWT HS256 de 7 dias, sem refresh nem revogação. Sessão por cookie `httpOnly` com `SameSite=Lax` (CSRF mitigado também por CORS sem credenciais e JSON com preflight); `GET /feed/trending?user=` identifica o dev sem token, como o original.
- **Sem defesa de infraestrutura contra abuso volumétrico**: a conta tem limite de concorrência 10 sem reserva possível e a Function URL não tem WAF. Sobram o rate limiting da aplicação (login e criações; contagem aproximada sob concorrência) e o alarme de custo. Por isso a URL do deploy não está publicada aqui.
- **Latência**: Lambda frio sem banco 1,64 s, acima da meta de 1,5 s (Fase 7).
- O oráculo v1 cobre 27 das 30 operações; `GET /search` e `GET /feed/subscriptions` são validados contra o contrato.
- O Free Plan da AWS encerra a conta ao expirar; a decisão de fim de vida (upgrade ou desligamento por [`specs/teardown.md`](./specs/teardown.md)) ainda não foi tomada.

## Documentação

- [`plan.md`](./plan.md): plano spec-driven fase a fase.
- [`specs/README.md`](./specs/README.md): estado de cada artefato, com o que foi verificado local e no deploy real.
- [`CLAUDE.md`](./CLAUDE.md): orientações usadas com o Claude Code durante o desenvolvimento (transparência sobre o processo).
