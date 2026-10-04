# Plano de Implementação (Spec-Driven Design) — DevFinder API Laravel (`php-laravel`)

> Reescrita do `php-codei` (aqui chamado de **v1**: CodeIgniter 4 + MySQL, monolito em Render)
> como um **projeto novo**, `php-laravel`: **Laravel + PostgreSQL (Neon) em AWS Lambda via Bref**,
> mantendo o **mesmo contrato público** de `devfinder-api` (devs, channels, videos, likes/follows,
> GitHub OAuth) e o objetivo de **custo zero**. O `php-codei` fica **intocado** ao lado, como
> referência e oráculo de paridade.
>
> Metodologia: **spec-driven design com PR por fase**, a terceira iteração do método —
> `../serverless` (spec antes do código, direto em `main`) → `../php-codei` v1 (PR por fase,
> reprodução do CI) → `../reactjs` (critérios de revisor sênior escritos antes do código).
> Esta versão acrescenta o que esses projetos e o projeto `transcript` ensinaram **na prática**
> e que aqui vira **gate verificável** (ver "Lições herdadas"), não recomendação.
>
> O plano e as specs do v1 continuam em `../php-codei/` (plano: `../php-codei/plan.md`; referência
> de comparação: commit `3229c4c`, `HEAD` de `main` em 2026-10-02). O v1 é o **oráculo** da paridade.
>
> **Prioridade do projeto**: o que a **comunidade PHP/Laravel espera de um desenvolvedor sênior** em
> 2026. Os padrões do projeto `transcript` entram só quando coincidem com a convenção da comunidade;
> quando conflitam (ex.: Repository pattern), **vence a convenção**, com ADR.
>
> **Notas locais, fora do Git** (decisão do usuário, 2026-10-02): arquivos com sufixo `__` (a comparação com o
> `transcript`, a lista de critérios de revisão e as versões anteriores deste plano) ficam só neste diretório,
> cobertos pelo `.gitignore`. **Este plano e as specs não dependem deles nem os linkam**: o que governa as fases
> está aqui (seção 4) e em `specs/`.

## Status

| Fase | Nome | Tamanho (estimativa relativa) | Status |
|---|---|---|---|
| 0 | Especificação, ADRs, oráculo v1 e critérios de revisão (inclui spike inicial S1–S3) | M | **concluída em conteúdo** (2026-10-03); falta só o PR do `fase-0b-spike` (ver `specs/fase-0-especificacao.md`). Adiados por precisarem de esqueleto: ferramenta de contrato, DTO, teste de arquitetura, UI de `/docs` e ADRs 0005 a 0008 (Fase 2a) e ADR 0004 (Fase 2b) |
| 1 | Modelo de dados (PostgreSQL) e orçamentos de query | M | **concluída** (PR #3 mergeado em 2026-10-03) |
| 2a | Esqueleto local: qualidade, CI, teste de arquitetura, erros, `/docs` e `config:check` | M | **concluída** (PR #4 mergeado em 2026-10-03) |
| 2b | Esqueleto na nuvem: Bref + Neon + deploy + guardrails de custo + spikes S4 a S7 (gate G0) | L | **concluída** (PR #5 mergeado em 2026-10-03): deploy real feito e verificado; OIDC validado no deploy do PR #6 (2026-10-03; o `sub` imutável exigiu corrigir a confiança da role); pendente a ADR 0004 |
| 3 | Endpoints públicos de leitura | M | **concluída** (PR #6 mergeado em 2026-10-03; aceite no deploy real em 2026-10-03, `specs/execucao-fase-3.log` seção 7): 10 rotas, 179 testes, G3 nulo local e no deploy real (30 capturas), orçamento de queries testado |
| 4 | Autenticação (GitHub OAuth + JWT) | M | **implementada local, aguardando o login real e o PR** (branch `fase-4-autenticacao`, `specs/fase-4-autenticacao.md`): `/auth/github`, callback com `state`, `/me`, middlewares, personalização, rate limiting; 259 testes, G3 nulo (40 capturas). SSM, variável `APP_WEB_URL` e política da role feitos; ADR 0013 aceita. Falta: callback do OAuth App na Function URL, login real |
| 5 | Endpoints autenticados de escrita e relacionamento | M | pendente |
| 6 | Ingestão em lote (agendada) | M | pendente |
| 7 | Observabilidade, desempenho, segurança e verificação contra o deploy real | L | pendente |
| 8 | Fechamento do `php-laravel` (checklist de long tail, README honesto, plano de fim de vida) | S | pendente |

Os tamanhos são estimativas relativas para ordenar o esforço, não prazos.

## Layout do workspace

```
/home/marcelo/Desktop/coding/published/
├── php-laravel/       ← você está aqui (plan.md, specs/, código Laravel a partir da Fase 2)
├── php-codei/         ← v1 (CodeIgniter 4/MySQL) — oráculo de paridade, não alterar
├── devfinder-api/      ← fonte do domínio e do contrato (Express + MongoDB) — não alterar
├── devfinder-next/     ← frontend consumidor — contrato público não pode quebrar; não alterar
├── serverless/         ← 1ª arquitetura (AWS Lambda/DynamoDB, Node) — não alterar
└── reactjs/            ← 3ª arquitetura (frontend Next.js) — não alterar
```

Caminhos como `devfinder-api/src/...` leem-se como `../devfinder-api/src/...`.

**Repositório**: o `git init` já foi feito (branch local `master`, **sem commit**, sem remoto). Antes do
primeiro PR: fazer o commit inicial (**só a pedido explícito**; hoje o repositório está no branch `fase-0-especificacao`, ainda sem commit, e `main` só passa a existir depois do commit inicial). O remoto
**já existe**, criado pelo usuário: `https://github.com/MarceloVilela/devfinder-laravel`, registrado como `origin`
na configuração local (nenhum push feito). O remoto do v1 (`devfinder-codeigniter`) não é tocado. O
`.gitignore` já ignora `CLAUDE.local.md`; a Fase 0 acrescenta `**/*__.*` e `*.private.*`.

**Estrutura de `specs/`** (projeto novo, sem colisão com o v1):

| Caminho | Conteúdo |
|---|---|
| `specs/fase-0-openapi.yaml` | **Contrato único**, copiado de `../php-codei/specs/fase-0-openapi.yaml` na Fase 0 e revalidado; qualquer divergência se corrige aqui primeiro |
| `specs/acceptance/*.http` | Casos de aceite copiados de `../php-codei/specs/acceptance/` na Fase 0; rodam contra `v1`, `local` e `real` |
| `specs/fase-N-nome.md` | Spec de cada fase e, ao lado, `execucao-fase-N.log` com a evidência |
| `specs/adr/` | Registro de decisões (`NNNN-título.md`), como no projeto `transcript` |
| `specs/spikes/` | Resultado de cada spike, com número medido |

---

## 1. Decisões que revertem ou mudam o v1 (D1 a D4) e de arquitetura de código (D5 a D8)

O v1 decidiu o contrário de D1 a D3, **de propósito** (`portfolio__.md`, 2026-08-23); mudar é
legítimo, mas precisa de justificativa escrita e de critério de reversão. D5 a D8 corrigem o que o v1
fez **sem decisão registrada** (ver a comparação). Todas viram ADR na Fase 0.

| # | Decisão (`php-laravel`) | Decisão v1 | Por que mudar | O que invalidaria a decisão |
|---|---|---|---|---|
| D1 | **Laravel** e **PHP** (versões estáveis vigentes, fixadas no ADR; preferir PHP 8.4 ou mais novo se houver camada Bref, pois o 8.3 já saiu do suporte ativo, a verificar) | CodeIgniter 4 | Maior ecossistema para o alvo (Bref tem guia próprio de Laravel e o pacote `laravel-bref` ajusta log, sessão e `storage` para o Lambda); ferramentas de qualidade e de contrato de API mais maduras | Bloqueio técnico nos spikes S1/S2 |
| D2 | **PostgreSQL no Neon** (plano gratuito) | MySQL/MariaDB (depois TiDB Cloud no Render) | Banco relacional gratuito acessível de fora de VPC, sem NAT nem RDS; o Laravel suporta Postgres de fábrica | S2/S3 mostrarem conexão ou cold start inviáveis (fallback: Supabase). **Região**: Lambda e projeto Neon na **mesma região AWS** (ADR de região na Fase 0; sem isso cada query paga latência de rede e o G1 não fecha) |
| D3 | **Serverless: AWS Lambda + Bref** | Monolito PHP-FPM + Nginx em container (Render) | O objetivo agora é rodar na AWS dentro do crédito do Free Plan sem pagar hospedagem fixa | Custo real maior que o previsto no S6, ou cold start fora da meta (G1) |
| D4 | **Entrada HTTP: Lambda Function URL** (hipótese) | — | Sem custo adicional além do Lambda; o API Gateway REST não é gratuito na conta (`../serverless/specs/aws-pending__.md`) e o HTTP API custa US$ 1 por milhão de requisições | S6 revelar limitação (sem throttling, sem WAF, sem domínio próprio) que o rate limiting da aplicação não cubra |
| D5 | **Organização por feature** (`app/Features/<Feature>/{Actions,Data,Http,Models,Policies,Queries,Exceptions}`) com **uma Action por caso de uso**; DTOs `readonly`; controller só orquestra HTTP | Camada técnica (`Controllers`, `Models`, `Libraries`); regra de caso de uso dentro do controller | Vertical slices com Actions são o padrão descrito pela comunidade Laravel para APIs que crescem; o v1 tinha `store` com regra, integração externa e corpo de erro no controller | O teste de arquitetura (Fase 2a) mostrar cerimônia sem valor. **Exceção já definida**: rota de **leitura sem regra de negócio** pode chamar um objeto de `Queries/` direto do controller (sem Action repasse, que o sinal 11 desconta); Action é obrigatória onde houver regra, escrita ou integração |
| D6 | **Sem Repository genérico**: Eloquent com scopes e objetos de consulta (`Queries/`); repositório só com ADR | Models e Query Builder do CI4 chamados direto do controller | No Laravel o Eloquent já é a camada de acesso, e a comunidade critica repositórios que reimplementam o Eloquent. **Diverge de propósito do `transcript`** | Necessidade real de trocar o armazenamento ou de testar regra sem banco que Actions e Pest com banco real não resolvam |
| D7 | **Documentação**: YAML spec-first como fonte de verdade, UI servida em `/docs`, teste de contrato a cada PR; **Scramble só como detector de divergência** (a avaliar) | YAML sem UI e sem teste de contrato | A paridade de contrato é o objetivo; sem teste, o YAML diverge da API em silêncio | Scramble ou validador incompatível com Bref/Lambda (Fase 0/2): fica só o teste de contrato |
| D8 | **Erros**: exceções tipadas por feature, renderizadas em um ponto (`bootstrap/app.php`), **preservando o formato de erro do contrato** (`errorMessage`, `error`…) | Corpo de erro montado à mão em cada controller | Erro tratado em um lugar só e testável; o formato público não pode mudar | Contrato de erro do OpenAPI inconsistente a ponto de exigir ADR de mudança aprovada |

**Custo de portfólio, assumido às claras**: o v1 argumentou que repetir "Lambda + API Gateway"
mostraria menos variedade do que um monolito. Este projeto troca a linguagem, o banco e o modelo de
dados em relação ao `../serverless` (PHP/Eloquent/PostgreSQL relacional vs. Node/DynamoDB
single-table), então a comparação continua útil, mas a história "monolito vs. serverless" do v1
deixa de existir. Isso entra no README final (Fase 8), não fica escondido.

**Conflito com a regra de custo do v1** (`CLAUDE.md`, "Regra de custo"): exigia camada
"genuinamente sempre-gratuita". Na conta AWS atual isso **não é totalmente atingível**: o Free
Plan (US$ 100 de crédito + até US$ 100 por tarefas, 6 meses) **encerra a conta** ao expirar
(`../serverless/specs/aws-pending__.md`). Reformulação adotada: *custo zero durante o Free Plan,
com plano de fim de vida decidido até o dia 150* (Fase 8, "Fim de vida"). Registrar o ADR e
atualizar o `CLAUDE.md`.

---

## 2. Lições herdadas (gates, não recomendações)

Cada linha é um incidente **real** do workspace e vira uma verificação concreta neste plano.

| Lição | Origem | Gate neste plano |
|---|---|---|
| Rodar o CI localmente **não** vale se o `.env` local tiver mais chaves que o do workflow: `auth.jwtSecret` ausente derrubou 11 de 30 testes só no CI | `php-codei` v1, `specs/fase-7-observabilidade-testes.md` | Fase 2a: reproduzir o workflow **byte a byte** (container limpo + `.env` gerado pelo próprio workflow) antes de todo PR que toque CI, `composer.*`, `.env*` ou deploy |
| Conferir `echo $?`, **sem pipe**: PHPUnit imprimia "OK" mas saía com código 1 (aviso de cobertura virava warning) | `php-codei` v1, `CLAUDE.md` | Todo gate abaixo declara o código de saída esperado; scripts de CI usam `set -euo pipefail` |
| Variável de ambiente com **ponto** no nome sumiu em silêncio no Render; env var do Blueprint nunca criada | `php-codei` v1, `specs/deploy/fase-7-deploy-render.md` | Só nomes `[A-Z0-9_]`; comando `config:check` que valida a **lista completa** de chaves obrigatórias no CI **e** no boot; conferência da lista completa contra o `serverless.yml` depois do 1º deploy |
| Endpoint de diagnóstico de env em produção foi o único jeito de achar a causa | `php-codei` v1, mesmo arquivo | Em vez de endpoint, log estruturado **no boot** com os *nomes* (nunca valores) das chaves presentes e o resultado do `config:check` |
| N+1 só apareceu medindo: `GET /devs` fazia 126 queries para 30 itens | `php-codei` v1, Fase 7 | **Orçamento de queries por endpoint** (teste automatizado) e `Model::preventLazyLoading()` fora de produção, desde a Fase 3, não na Fase 7 |
| Dois bugs do `devfinder-api` original só apareceram lendo o conteúdo: `POST /channels` criava `Dev` sem `username`; o OpenAPI não documentava `userGithub`/`avatar` | `php-codei` v1, `specs/fase-5-escrita-relacionamentos.md` | Fase 0: **auditoria do original** (ler o conteúdo, não a forma) gera lista de bugs herdados com decisão para cada um |
| Bugs de framework só apareceram rodando de verdade (`find(false)` devolvendo a tabela inteira, `*/g` em comentário) | `php-codei` v1, Fases 3 e 5 | Aceite = rodar os `.http` reais e guardar a **saída** como evidência versionada (`execucao-fase-N.log`), nunca "parece ok" |
| Deploy que dorme ou demora dezenas de segundos é um dos primeiros sinais para o revisor | `reactjs/criterios-revisor-senior-2026.md`, itens 16 e 14 | Meta de latência a frio e a quente medida (G1) e **registrada no README**, inclusive o cold start do Neon |
| `fly launch` criou em silêncio um Postgres de US$ 38/mês | `transcript`, 2026-10-02 | **Allowlist de tipos de recurso** no template do CloudFormation (falha o CI se aparecer RDS, NAT, API Gateway não previsto…); revisar `serverless package` antes do 1º deploy |
| Migration rodada de dentro do deploy, e URL de pooler dá problema com migration | `transcript` (`release_command`) e projeto de referência (`DIRECT_DATABASE_URL`) | Migrations rodam em passo **separado e anterior** ao deploy, com a URL **direta** do Neon; runtime usa a URL pooled; migrations sempre retrocompatíveis (expandir → migrar → contrair) |
| Retry curto demais (5 s, 10 s) contra rate limit de 1 minuto esgotou as tentativas | `transcript`, `docs/prompts-desenvolvimento/tokens__.md` | Fase 6: chamadas externas (JSONBin, GitHub) com timeout, retry com backoff e respeito a `retry-after`; falha definitiva **registrada** em log |
| Token de deploy, chave da AWS e dados de cartão ficaram colados em notas locais | `fyoker-tier.md` | CI usa **OIDC** GitHub→AWS (sem chave de longa duração, a validar no S5); nenhum segredo em arquivo versionado nem em nota; varredura de segredos no CI |
| Remover recursos exige plano com nomes explícitos e ordem; havia outro app na mesma conta | `transcript`, `fly.io-destroy__.md` | Fase 8: `specs/teardown.md` escrito **antes** do primeiro deploy, sem curingas |
| Resposta de erro sem causa visível custou horas (`Query failed: Database connection error`) | `transcript` | Erros de infraestrutura distinguidos de erros de dado (503 vs 404); log com causa e id de requisição |
| Documentos do projeto apontando para arquivo que mudou de nome | `php-codei/CLAUDE.md` cita `portfolio.md`, hoje `portfolio__.md` | Fase 0: toda referência cruzada em `CLAUDE.md` e `specs/README.md` conferida contra o arquivo real (existe e tem o nome citado) |
| Controller com regra de negócio, chamada HTTP externa e corpo de erro montado à mão; `service()`, `model()` e `new` no lugar de injeção; `present()` duplicado | `php-codei` v1 (`VideoController::store`, `ChannelController::store`) | Fase 2a: **teste de arquitetura** impede controller de tocar Eloquent (exceto `Queries/` em leitura sem regra, D5), cliente HTTP ou `new` de classe de domínio; Fases 3 a 5: Action onde houver regra, escrita ou integração |
| OpenAPI sem UI e sem teste que o valide: o contrato pode divergir da API em silêncio | `php-codei` v1 (nenhuma referência em `app/` nem em `tests/`) | D7: UI servida e teste de contrato a cada PR (a partir da Fase 3) |
| Sem análise estática nem formatador (`composer.json` só tem PHPUnit no dev) | `php-codei` v1 | Fase 2a: Pint, Larastan e `composer validate --strict` no CI desde o primeiro PR |
| Quase sem teste unitário (1 de 6 arquivos de teste): a lógica estava presa a controller e banco | `php-codei` v1 | Actions testáveis por unidade, com dependências injetadas; pirâmide na seção 8 |

---

## 3. Metodologia — spec-driven + PR por fase + gates

Cada fase segue a mesma sequência:

1. **Spec**: `specs/fase-N-nome.md` com comportamento esperado, decisões e critério de aceite,
   escrita **antes** de qualquer código. Decisão em aberto na spec vira ADR, nunca improviso.
2. **Branch** `fase-N-nome` a partir de `main`.
3. **Implementar** na ordem **spec → teste → implementação**.
4. **Verificar** (gates abaixo) e guardar a evidência em `specs/execucao-fase-N.log`.
5. **Pull Request** `fase-N-nome → main`, só quando o critério de aceite já bate. **Só quando o
   usuário pedir explicitamente** (regra do `CLAUDE.md`): implementar até o critério bater é
   trabalho autônomo; commit, push e PR são decisão humana. Sem co-autor do Claude em commits e PRs.
6. **Merge e avanço**: a fase seguinte só começa com o PR anterior mergeado.

### Definição de pronto (vale para toda fase)

- [ ] Spec aprovada; ADRs novos escritos.
- [ ] Testes escritos antes do código e verdes; **código de saída conferido sem pipe** (`echo $?` → `0`).
- [ ] CI reproduzido byte a byte localmente (container limpo, `.env` gerado pelo workflow).
- [ ] Contrato: respostas validadas contra `specs/fase-0-openapi.yaml` (a partir da Fase 3).
- [ ] Orçamento de queries respeitado (a partir da Fase 3).
- [ ] Teste de arquitetura verde (camadas e dependências entre features, a partir da Fase 2).
- [ ] Pint, Larastan (nível do ADR) e `composer validate --strict` com código de saída `0`.
- [ ] Nenhum controller com regra de negócio, acesso direto ao Eloquent (exceto `Queries/` em leitura sem regra) ou chamada HTTP externa.
- [ ] Sem mass assignment (`$fillable` explícito, `$request->validated()`), sem SQL cru nem `orderBy` com coluna vinda da requisição (teste com entrada hostil em `/search` e em qualquer ordenação), sem exceção engolida (regra de análise estática ou `arch()`).
- [ ] `composer audit` sem vulnerabilidade **nem pacote abandonado** sem justificativa (configurar para falhar; a verificar na versão do Composer).
- [ ] Commits convencionais; `CHANGELOG.md` atualizado quando houver mudança visível.
- [ ] `config:check` verde; nenhuma chave nova fora da lista documentada.
- [ ] Inventário de custo (somente leitura) sem recurso inesperado.
- [ ] Evidência real versionada (saída dos `.http`, medições); limitações novas anotadas no README.
- [ ] Sem segredo no diff (varredura) e sem arquivo morto versionado.

### Gates de arquitetura

| Gate | Quando | Critério (metas propostas, a calibrar no spike) | Se falhar |
|---|---|---|---|
| **G0** Viabilidade | spike inicial na Fase 0 (S1–S3) e fim da Fase 2b (S1–S7) | S1–S7 com resultado medido; custo real do deploy visível | Replanejar D1–D4 antes de escrever domínio (e, no spike inicial, antes das migrations da Fase 1) |
| **G1** Latência | Fase 2b e Fase 7 | metas **iniciais e frouxas**: quente p95 ≤ 500 ms; frio (Lambda) ≤ 5 s; frio de Lambda **e** Neon ≤ 10 s. Depois da primeira medição no S3, **apertar** os alvos e registrar no ADR (um revisor lê 5 a 10 s como lento) | Reduzir boot (config/route cache, OPcache, dependências); só depois considerar mitigação paga, que **não** é aceita |
| **G2** Custo | todo fim de fase | US$ 0 de cobrança fora do crédito; nenhum recurso fora da allowlist | Parar a fase e investigar antes de continuar |
| **G3** Paridade | Fases 3, 5, 6 e 7 | Diferença v1×`php-laravel` nula nos `.http` **sobre o mesmo dataset de paridade** (definido na Fase 0), depois de normalizar ids e datas (ver "Estratégia de testes"). Divergência só se estiver no registro de divergências aprovadas | Corrigir o `php-laravel`, ou registrar a divergência como ADR **aprovada** |
| **G4** Arquitetura | toda fase com código | Teste de arquitetura verde; nenhum controller fora do contrato de camadas da seção 6 | Corrigir antes do PR; mudar a regra só por ADR |

### Assistência com IA

| Projeto | Ferramentas | Estado | Para que serviu (registros do projeto) |
|---|---|---|---|
| `serverless` | nenhuma registrada | n/a | n/a |
| `php-codei` (v1) | Context7 MCP, skill de CodeIgniter, DeepWiki | instaladas e testadas na Fase 0 | API de migrations, versão LTS do MySQL, comportamentos do framework |
| `reactjs` | Context7, shadcn/ui MCP, 21st.dev Magic MCP | só planejadas, nada instalado | n/a |
| `php-laravel` | Context7 (Laravel, Bref), skills publicadas de Laravel, DeepWiki (repositórios do Laravel e do Bref) | instalar e testar na Fase 0 | a definir pelas perguntas da Fase 0 |

- **Regra**: uma ferramenta só entra se responder uma pergunta concreta listada na Fase 0, com teste real
  e o resultado registrado (não basta citar que se usa IA). **Não é item de aceite da Fase 0**: tempo limitado,
  sem bloquear o PR.
- **Limite conhecido do v1**: os achados caros (CI, variáveis do Render, N+1) vieram de **rodar e medir**,
  não de consulta. Essas ferramentas não substituem os `.http` reais, o CI reproduzido e o deploy.
- A cobertura do Context7 e do DeepWiki para Laravel e Bref **não foi verificada**; conferir na Fase 0.

---

## 4. Critérios de revisão sênior para PHP/Laravel em 2026

Mesmo método do `../reactjs/criterios-revisor-senior-2026.md`: o que desconta ponto se
encontrado e o que é piso. A lista que governa as fases é a de baixo (20 sinais e o piso); a versão
longa, com fontes, é uma nota local não versionada. A Fase 0 **revisa** a lista abaixo contra o que as fases 0 e 1
descobrirem.

**Sinais que um revisor sênior nota rápido (cada um é um item de checagem de PR)**

1. Controller com validação e regra de negócio dentro, em vez de Form Request, ação/serviço e Resource.
2. N+1 em endpoint de listagem; `preventLazyLoading` ausente fora de produção.
3. Model devolvido direto no JSON, expondo colunas internas, em vez de API Resource com contrato explícito.
4. Autorização feita à mão em `if`, sem Policy/Gate; ou rota de escrita sem checagem de dono.
5. Ausência de rate limiting em autenticação; `APP_DEBUG=true` fora do ambiente local.
6. `declare(strict_types=1)` ausente; análise estática ausente ou em nível baixo.
7. Zero teste, ou testes que não rodam no CI a cada PR; CI que não falha de verdade.
8. Segredo ou token em log, query string, arquivo versionado ou nota local.
9. Migration destrutiva no mesmo passo do deploy, sem estratégia de retrocompatibilidade.
10. Chamada externa sem timeout, sem retry controlado e sem tratamento de erro distinguível.
11. Contrato de API que diverge do documentado (OpenAPI desatualizado, shape diferente do consumidor).
12. README com link de demo morto, badge desatualizado ou limitações omitidas; arquivo morto versionado.
13. Deploy que não existe ou que demora dezenas de segundos sem que o autor tenha medido e dito.
14. Config lida com `env()` fora dos arquivos de `config/` (quebra com `config:cache`, que o Lambda usa).
15. Repository genérico sobre Eloquent que reimplementa o que o Eloquent já faz.
16. Facade, `app()` ou `new` dentro de regra de negócio (service locator), em vez de injeção pelo construtor.
17. Mass assignment: `$guarded = []`, `create($request->all())` ou campo sensível em `$fillable`.
18. SQL cru ou `orderBy` com coluna vinda da requisição (`DB::raw`, `whereRaw`), anulando a parametrização.
19. Exceção engolida (`catch` vazio), `try/catch` genérico ou erro de infraestrutura virando 404 ou 200.
20. `composer.lock` ausente, `composer audit` que não roda, pacote abandonado ou vulnerável, versões soltas.

**Piso (esperado por padrão em 2026)**

- Form Requests, API Resources, Policies; controllers finos; regra de negócio em **Actions** testáveis, com dependências injetadas.
- `strict_types` em todo arquivo (Pint), Larastan em nível alto (meta 9, **sem baseline global**: o projeto é novo e não tem legado).
- Pest (ou PHPUnit) com banco real (PostgreSQL de serviço no CI), teste de contrato OpenAPI e teste de
  orçamento de queries.
- Erros com formato único e código HTTP correto; erro de infraestrutura ≠ erro de dado.
- Logs estruturados em JSON com id de requisição; nada sensível.
- Limitações técnicas conhecidas escritas pelo próprio autor, no README.

Procedência: itens 6 a 10 e 12 a 14 vêm de incidentes deste workspace; os demais, de guias de revisão de Laravel
(ver "Fontes"). O que vem de guias é só o que eles repetem, não pesquisa de adoção.

**Linha de base da comunidade PHP sênior em 2026** (a comparação detalhada é uma nota local não versionada)

| Área | Esperado | Neste projeto |
|---|---|---|
| Linguagem | PHP 8.3+ (meta 8.4+, a verificar no Bref), `declare(strict_types=1)`, tipos nativos em tudo, `readonly`, enums, DTOs e Value Objects no lugar de arrays | Pint (`declare_strict_types`), Larastan, DTOs `readonly` |
| Estilo e autoload | PSR-4; PER Coding Style (que estende e substitui o PSR-12); `.editorconfig` | Pint com preset PER (confirmar na Fase 0) |
| Análise estática | PHPStan/Larastan em nível alto, com o 9 citado como padrão-ouro | Meta nível 9 sem baseline global; exceção pontual com justificativa; nível final calibrado na Fase 2 |
| Arquitetura | Controllers finos, Actions ou Services, injeção pelo container, SOLID, Eloquent como camada de acesso, teste de arquitetura | D5, D6; Pest `arch()` ou PHPat (escolher na Fase 0); facades só nas bordas |
| Testes | Pest ou PHPUnit; cobertura da lógica de negócio (referência: 70% ou mais); teste de mutação como diferencial | Meta no ADR, medida com PCOV; **Infection** só nas Actions, como diferencial, não como gate |
| Dependências | `composer.lock` versionado, `composer validate --strict`, `composer audit` | Gates de CI |
| Refatoração | Rector para upgrades e regras de qualidade | Opcional: `rector --dry-run` no CI, sem aplicar |
| Versionamento | SemVer, `CHANGELOG`, commits convencionais (o v1 já usava `docs:`, `feat:`, `fix:`) | `CHANGELOG.md` mantido e tag `1.0.0` na Fase 8 |
| Segurança e observabilidade | OWASP API Top 10, rate limiting, logs estruturados (PSR-3), id de requisição | Fases 4 e 7 |

---

## 5. Objetivo e critérios de sucesso

**Objetivo**: a API do DevFinder em Laravel + PostgreSQL (Neon), em AWS Lambda via Bref, com
paridade funcional com `devfinder-api` e com o `php-codei` v1, **sem pagar nada**.

**Critérios de sucesso (todos mensuráveis)**

1. **Paridade de contrato**: toda operação de `specs/fase-0-openapi.yaml` implementada, e cada
   resposta validada contra o contrato nos testes.
2. **Paridade com o v1**: os mesmos `.http` de `specs/acceptance/` produzem respostas equivalentes
   no v1 e no `php-laravel`, depois de normalizar ids e datas (gate G3), **nas 27 operações que o v1 implementa**.
   As 3 que o v1 nunca implementou (`GET /search`, `GET /feed/subscriptions`, `POST /channels/refresh`; 404 no
   código e no Render, conferido em 2026-10-02) têm o `devfinder-api` e o contrato como oráculo.
   Divergências só no registro de divergências aprovadas (`specs/auditoria-original.md`).
3. **Latência**: metas do gate G1 medidas contra o deploy real e publicadas.
4. **Custo**: US$ 0 cobrado; allowlist de recursos respeitada; alerta de orçamento ativo; plano
   de fim de vida escrito.
5. **Qualidade**: Pint, Larastan (nível definido no ADR), Pest e `composer audit` verdes no CI a cada PR.
6. **Honestidade**: README com demo funcionando no primeiro clique, medições reais e limitações listadas.
7. Cada fase corresponde a um PR mergeado, com CI verde, referenciando sua spec e sua evidência.

**Fora de escopo (`php-laravel`)**: alterar o frontend ou apontá-lo para esta API (decisão de portfólio
herdada do v1 e do `serverless`); domínio próprio e WAF (custo); alta disponibilidade e
multi-região; migração de dados de produção do Mongo (só o seed do dump já usado no v1);
reescrever `devfinder-api`.

---

## 6. Arquitetura alvo (hipótese a confirmar nos spikes)

```
Cliente ──HTTPS──▶ Lambda Function URL ──▶ Lambda (Bref php-83-fpm + Laravel, fora de VPC)
                                              │   ├── PostgreSQL no Neon (URL pooled no runtime)
                                              │   ├── SSM Parameter Store (segredos)
                                              │   └── CloudWatch Logs (retenção curta, JSON)
EventBridge Scheduler ──▶ Lambda (artisan: ingestão agendada)

GitHub Actions: PR ─▶ Pint + Larastan + Pest (Postgres de serviço) + audit + allowlist
                main ─▶ migrate (URL direta do Neon) ─▶ deploy ─▶ smoke test ─▶ evidência
```

**Escolhas deliberadas (e o que ficou de fora)**

- **Sem VPC, sem RDS, sem NAT**: o Lambda fora de VPC alcança o Neon pela internet; NAT custa
  cerca de US$ 27 a 32 por mês e RDS gasta o crédito.
- **Sem API Gateway** (hipótese D4) e **sem SQS**: a ingestão é em lote e cabe no Lambda
  (fila `sync`); menos serviços, menos custo e menos superfície.
- **Sem S3 de aplicação**: o domínio não recebe arquivos enviados. O deploy do Serverless Framework
  cria, porém, um **bucket de artefatos** (e IAM e log groups); a allowlist os prevê e o S5 confirma o que
  o template gera de fato. Se a aplicação precisar de S3, vira ADR.
- **Sessão e cache sem disco**: o sistema de arquivos do Lambda é só leitura (exceto `/tmp`) e não é
  compartilhado entre instâncias. Autenticação é por JWT sem estado. **Cache de aplicação em `array` não serve para rate limiting**
  (o contador zera a cada requisição e o limite nunca dispara): o store do `RateLimiter` é decidido no S7
  (tabela de cache no Postgres, com o custo de uma query por requisição limitada, ou outra opção dentro da
  allowlist), com teste que prova o 429.
- **Segredos no SSM Parameter Store** (Standard, sem custo), nunca em variável de ambiente em
  texto no repositório. Lição do `serverless`: CloudFormation não cria `SecureString`, então o
  valor sensível é gravado fora do template. Verificar na Fase 2 se o Bref resolve parâmetros do SSM
  nas variáveis de ambiente (a confirmar na documentação).
- **Concorrência reservada do Lambda baixa** (valor definido no S6; contas novas costumam ter limite
  de concorrência pequeno e talvez não permitam reservar, **a verificar** e prever alternativa): limita ao mesmo tempo o custo
  em caso de abuso e o número de conexões abertas no Neon. Sem API Gateway e sem WAF, esta é a
  principal defesa de infraestrutura, junto com o rate limiting da aplicação.

**Estrutura de código (D5 a D8)**

```
app/
├── Features/
│   ├── Devs/        Actions/  Data/  Http/{Controllers,Requests,Resources}  Models/  Policies/  Queries/  Exceptions/
│   ├── Channels/    (mesma forma)
│   ├── Videos/
│   ├── Reactions/   (likes, dislikes, follow, ignore)
│   ├── Auth/        (GitHub OAuth, JWT, guards)
│   └── Ingestion/   (comando Artisan e rota manual; clientes externos atrás de interface)
└── Shared/          Exceptions/ (renderização)  Http/ (formato de erro)  Support/
routes/api.php       só mapeia rota → Controller, com o prefixo /v1
```

Regras de dependência, **verificadas por teste de arquitetura** (G4):

- `Http` → `Actions` → `Models`/`Queries`. **Controller não toca Eloquent, cliente HTTP nem `new` de classe de domínio.**
- `Shared` não importa nenhuma feature; uma feature usa outra só por Actions e Data.
- Cada rota segue **Form Request** (validação) → **Action** (regra) → **Resource** (formato do contrato).
- Integrações externas (GitHub, JSONBin) ficam atrás de uma interface, ligada no ServiceProvider da feature;
  nos testes entram `Http::fake` ou um fake da interface.
- Facades só nas bordas (rotas, providers, config); as regras recebem dependências pelo construtor.
- Versão (`/v1`) só na rota e na documentação; nenhum nome de classe a cita.

---

## 7. Fases

### Fase 0 — Especificação, ADRs, oráculo v1 e critérios de revisão

**Objetivo**: decidir tudo o que é barato de decidir agora, criar o projeto e fixar o v1 como referência.

| Artefato / tarefa | Conteúdo |
|---|---|
| Spike inicial (tempo limitado) | S1–S3 mínimos **antes da Fase 1**: Laravel + Bref respondendo no Lambda, `pdo_pgsql` conectando no Neon e cold start medido. Se falhar, replanejar D1/D2 antes de qualquer migration (resultado em `specs/spikes/`) |
| ADR de versões e região | PHP, Laravel e camada Bref (preferir PHP 8.4+ se existir; verificar); região AWS do Lambda **igual** à do Neon |
| Dataset de paridade | Definir o conjunto de dados **idêntico** carregado no v1 e no `php-laravel` para o diff do G3 (seed sintético versionado; o dump real só por caminho relativo e local) e o **registro de divergências aprovadas** (bugs herdados corrigidos de propósito) |
| Referência v1 | `../php-codei` fica **intocado**; registrar o commit de referência (`3229c4c`) e o comando para subi-lo localmente (`docker compose` do v1). Nada é movido nem editado lá |
| ADRs D1–D8 | `specs/adr/0001..0010` (D1–D8, versões e região, regra de custo) com contexto, decisão, alternativas, custo e **critério de reversão** |
| Arquitetura de código | `specs/arquitetura-alvo.md`: estrutura e regras da seção 6, com **um caso de uso de ponta a ponta no papel** (uma rota de leitura e uma de escrita); escolha de DTO (`readonly` ou `spatie/laravel-data`) e do teste de arquitetura (Pest `arch()` ou PHPat), cada um **testado com um exemplo real** |
| Contrato de erro | Inventariar todos os formatos de erro do v1 (`errorMessage`, `error`, status 400, 409…) e mapear cada um a uma exceção tipada (D8) |
| Documentação do contrato | Escolher a UI de `/docs` e avaliar o Scramble como detector de divergência (D7), com um teste real |
| Auditoria do original | Ler `devfinder-api` (conteúdo, não só forma) e o v1; listar bugs herdados e a decisão para cada um (ver "Lições": `POST /channels`, campos faltando no OpenAPI) |
| Contrato | Copiar `../php-codei/specs/fase-0-openapi.yaml` e revalidar contra o comportamento real do v1; qualquer discrepância é corrigida **no contrato primeiro** |
| Casos de aceite | Copiar `../php-codei/specs/acceptance/*.http` e `http-client.env.json` (**nunca** `http-client.private.env.json`, que pode ter segredo); acrescentar o ambiente `v1`; definir a normalização de ids/datas para a comparação v1×`php-laravel` |
| Oráculo | Confirmar como rodar o v1 para comparação: o deploy do Render pode já não existir (não verificado) — fallback: `docker compose` do v1 a partir do commit de referência |
| Critérios de revisão | Revisar os sinais e o piso da seção 4 contra o que as fases 0 e 1 descobrirem, e transformar cada item em verificação de PR quando possível |
| Ferramentas de contexto (tempo limitado, não bloqueia o PR) | Avaliar **e testar** Context7 (`resolve-library-id` para `laravel/framework` e Bref) e skills publicadas de Laravel; registrar resultado, não só citar |
| Ferramenta de contrato | Escolher **uma** entre Spectator, `kirschbaum-development/laravel-openapi-validator` e Gesso, com tempo limitado: testar a mais provável e só testar outra se ela falhar |
| Documentos do projeto | Criar `CLAUDE.md` (stack, regra de custo reformulada, lições herdadas, regras de commit e PR), `CLAUDE.local.md` (não versionado), `README.md`, `.gitignore` (inclui `**/*__.*` e `*.private.*`) e `specs/README.md`; documentos `__` ficam locais e **nenhum arquivo versionado os referencia**; conferir toda referência cruzada |
| Dados de teste | O dump real está em `../php-codei/specs/seed/` (**gitignored**, risco de marca e LGPD documentado); usá-lo só por caminho relativo, **sem copiar para este repositório**; definir um seed sintético mínimo versionado |

**Critério de aceite**: specs e ADRs aprovados (as que dependem de esqueleto, ver abaixo, ficam como `proposta`); spike inicial S1–S3 com
resultado; toda operação do OpenAPI no escopo com pelo menos um caso de aceite; `CLAUDE.md` sem referência quebrada; commit de
referência do v1 registrado; repositório com `main` e remoto criados; dataset de paridade definido. PRs da Fase 0 mergeados.

**Adiado para depois do esqueleto** (decisão do usuário, 2026-10-03: "a Fase 2 envolve esqueleto"): a **ferramenta de contrato** (Spectator,
`laravel-openapi-validator` ou Gesso), a escolha de **DTO**, do **teste de arquitetura** (Pest `arch()` ou PHPat), da **UI de `/docs`** e do
**Scramble** passam a ser entregáveis da **Fase 2a**; as **ADRs 0005 a 0008** são aceitas na 2a, com o código real; a **ADR 0004** (Function
URL, defesa contra abuso) é aceita na **Fase 2b**.

**Riscos**: o oráculo do v1 indisponível (mitigação acima); o escopo da auditoria crescer
(limitar a rotas do OpenAPI).

---

### Fase 1 — Modelo de dados (PostgreSQL) e orçamentos de query

Mongo usa 3 coleções com arrays embutidos; o v1 normalizou em MySQL. Aqui o ponto de partida é o
`../php-codei/specs/fase-1-data-model.md`, adaptado para Postgres, **com decisão escrita antes de qualquer migration**.

| Decisão (ADR) | Opções | Observação |
|---|---|---|
| Formato dos `id` expostos | **string** (D-7, aprovada em 2026-10-02): o contrato e o frontend dizem string; o v1 devolve inteiro. O id interno pode seguir inteiro ou virar ULID/UUID; o JSON sempre expõe string | O v1 expõe inteiro, então o G3 normaliza |
| Busca (`GET /search`) | `ILIKE` simples, `pg_trgm`, full-text do Postgres | Medir no dump real (500 vídeos, 186 canais, 40 devs no v1); não otimizar além da escala |
| Likes/follows | tabela de junção com chave composta; contagem por `COUNT` | Idempotência com `INSERT … ON CONFLICT DO NOTHING` |
| Paginação | só `page` no contrato (30 fixo, sem `limit`); comportamento **fora do intervalo** definido (o `Pager` do CI4 fazia clamp) | Registrar o comportamento, não herdar por acaso |
| Tipos | `timestamptz`, `bigint` ou `uuid`, `jsonb` só se justificado | Tipos estáveis no JSON (o v1 teve string vs. inteiro no MySQLi) |
| Índices | um por consulta do contrato, justificado pela query | Sem índice "por via das dúvidas" |

**Orçamento de queries (novo em relação ao v1)**: a spec lista, para cada operação de leitura, o
**número máximo de queries** por requisição (por exemplo: lista de 30 itens ≤ 6 queries, **independente
do tamanho da página**). Esse número vira teste na Fase 3.

**Critério de aceite**: `specs/fase-1-modelo-de-dados.md` aprovado; migrations escritas para cada
tabela e **reversíveis**; cada operação do OpenAPI mapeada a uma query concreta com orçamento; PR mergeado.

---

### Fase 2 — Esqueleto andante (walking skeleton), em dois PRs

**Objetivo**: ter o caminho **completo e vazio** funcionando — código → CI → migration → deploy → smoke
test → custo — antes de escrever uma regra de domínio. É onde os incidentes do v1 (env, CI, deploy) são
evitados, e onde os riscos de D1–D4 são medidos. Dividida em **2a** (tudo o que roda local e no CI) e
**2b** (nuvem), para manter cada PR revisável.

**Fase 2a — entregáveis (PR `fase-2a-esqueleto-local`)**

- Laravel na raiz de `./php-laravel` (`composer create-project`), sem subpasta.
- Qualidade: Pint com `strict_types` e preset PER, Larastan (meta nível 9, sem baseline global), Pest,
  `composer validate --strict` e `composer audit`; `preventLazyLoading` fora de produção.
- **Escolhas adiadas da Fase 0, decididas aqui com exemplo real**: ferramenta de contrato (**uma** entre Spectator, `laravel-openapi-validator` e
  Gesso, cada candidata validada com um teste real), DTO (`readonly` próprio ou `spatie/laravel-data`), UI de `/docs` e Scramble. Cada escolha
  vira evidência em `specs/spikes/` e atualiza a ADR correspondente; ao fim, as **ADRs 0005 a 0008 passam a `aceita`**.
- **Teste de arquitetura** (Pest `arch()` ou PHPat) com as regras da seção 6, no CI desde este PR, sobre a
  estrutura de features vazia (`Health` como única feature).
- **Handler global de erros** (D8) e **UI do contrato em `/docs`** (D7) já no esqueleto, com um teste de
  contrato de exemplo contra uma operação existente do YAML (a rota raiz, se constar do contrato).
- `docker compose` local com **PostgreSQL e a aplicação** (o host **não tem `php` nem `composer`**, conferido em
  2026-10-02: todo comando PHP, `composer` e `artisan` roda em container; o modo Bref local depende do spike).
- `GET /health` (sem banco) e `GET /health/db` (consulta trivial ao Neon).
- `config:check` + log de boot com os nomes das chaves presentes.
- **Logs JSON com id de requisição** (sem dado sensível) já no esqueleto, e `ci.yml` (PR) com todos os gates.
- **Critério de aceite 2a**: CI verde **reproduzido byte a byte**; `docker compose up` local; teste de
  arquitetura falhando de propósito num PR de teste (prova de que protege); ferramenta de contrato escolhida com evidência e ADRs 0005 a
  0008 aceitas; PR mergeado.

**Fase 2b — entregáveis (PR `fase-2b-esqueleto-nuvem`)**

- Deploy real via Serverless Framework + Bref; segredos no SSM; logs com retenção curta.
- `deploy.yml` como no diagrama da seção 6; **smoke test falha o pipeline** e já assere: `APP_DEBUG=false`,
  CORS restrito (origem da spec, nunca `*`), cabeçalho de id de requisição e nenhum stack trace em erro.
- **Role de deploy** com escopo mínimo documentado (confiança restrita a repositório e branch); a
  `DIRECT_DATABASE_URL` da migration é um secret do GitHub e entra na lista de segredos do `config:check`.
- **Guardrails de custo**: AWS Budgets com alerta em US$ 1; allowlist de tipos de recurso no template
  (incluindo o bucket de artefatos do Serverless); inventário de custo somente leitura (script
  documentado) e `specs/teardown.md` rascunhado.

**Spikes (cada um com pass/fail e número medido em `specs/spikes/`; S1–S3 rodam primeiro, em versão mínima, na Fase 0, e são reconfirmados aqui)**

| # | Pergunta | Passa se | Se falhar |
|---|---|---|---|
| S1 | Laravel + Bref sobem no Lambda com Function URL e respondem? Pacote ≤ limite do Lambda (250 MB descompactado)? | `GET /health` 200 a frio e a quente | Reavaliar D3/D4 |
| S2 | O `pdo_pgsql` da camada Bref conecta no Neon? (SNI exige libpq ≥ 14; senão, passar `options=endpoint=<id>` na conexão) | `GET /health/db` 200 com `sslmode=require` | Fallback Supabase; ou outro driver |
| S3 | Quanto custa o frio de Lambda + Neon (que escala a zero após ~5 min de inatividade)? | Metas do G1 | Reduzir boot; revisar D2 |
| S4 | Conexões: pooled (runtime) e direta (migration) funcionam? O pooler (modo transação) tolera o que o Laravel usa (prepared statements, `SET`)? | Teste de carga leve sem erro de conexão | Ajustar `PDO` (emulação de prepare) ou usar a URL direta com limite de concorrência |
| S5 | OIDC GitHub→AWS substitui chave de longa duração no `deploy.yml`? O Serverless Framework da versão escolhida exige login ou chave própria (`SERVERLESS_ACCESS_KEY`)? O que o template gera (bucket de artefatos, IAM)? | Deploy sem `AWS_ACCESS_KEY_ID` em secret e lista exata de recursos criados, tudo dentro da allowlist | Chave com escopo mínimo + rotação documentada; ou outro empacotador (ex.: AWS SAM) por ADR |
| S6 | Function URL vs HTTP API: custo e limites reais (sem throttling, sem WAF)? A conta **permite** concorrência reservada (o limite de contas novas pode impedir)? Qual valor protege Neon e crédito? | Custo observado no Cost Explorer (lembrar do atraso de cobrança) e limite definido | Adotar HTTP API (cobra do crédito) ou rever D4; sem reserva possível, rate limiting e alarme viram a única defesa e isso vai para o README |
| S7 | Qual **store** faz o rate limiting funcionar entre instâncias do Lambda, dentro da allowlist e do orçamento de conexões? | Teste que dispara 429 no limite, a frio e a quente, com custo de query medido | Tabela de cache no Postgres com limite baixo; ou aceitar a limitação por ADR e documentá-la |

**Resultados já medidos na Fase 0** (`specs/spikes/`): S1 a S4 e S7 rodaram; S5 e S6 parciais (o `osls` dispensa login do Serverless; a conta tem
limite de concorrência 10 e **não permite reservar**; o cache `array` não limita, o cache em tabela do Postgres sim, ver ADR 0011). OIDC e a role
de escopo mínimo do CI seguem para a Fase 2b.

Observação sobre o Neon: o plano gratuito tem um teto de horas de computação por mês; manter o banco
sempre acordado com ping periódico pode consumir esse teto antes do fim do mês (conta a fazer no S3
com os números atuais da conta, não com os desta pesquisa).

**Critério de aceite 2b**: gate **G0** — S1 a S7 resolvidos com medição; **ADR 0004 aceita** (Function URL com a defesa contra abuso decidida,
dado que a conta não permite concorrência reservada, ADR 0011); deploy real respondendo
`GET /health` e `GET /health/db`; Budgets ativo; allowlist falhando de propósito num PR de teste (prova
de que protege); asserções do smoke test (`APP_DEBUG`, CORS, sem stack trace) verdes; PR mergeado.

---

### Fase 3 — Endpoints públicos de leitura

`GET /devs`, `GET /devs/{username}`, `GET /channels`, `GET /channels/{searchQuery}`, `GET /feed/trending`,
`GET /feed/channel`, `GET /video/{idYoutubeWatch}`, `GET /description/feed`, `GET /description/category`,
`GET /search` (auth opcional; **o v1 não tem esta rota**, o oráculo é o `devfinder-api`; entra no escopo
conforme a decisão do usuário, ver `specs/fase-0-especificacao.md`).

- **Action onde houver regra** (ex.: `/search` com personalização por usuário); **leitura sem regra**
  (`GET /devs/{username}`, listas simples) chama um objeto de `Queries/` direto do controller (exceção do
  D5). Entrada por **Form Request** → DTO `readonly`, saída por **API Resource** com o shape do contrato.
  O controller só orquestra.
- `GET /search` e qualquer ordenação: **lista branca** de colunas e termo sempre parametrizado; teste com
  entrada hostil (aspas, `%`, `_`, nome de coluna inválido).
- Cuidado herdado do v1: segmento de rota com `/` ou `%2F`, tipos estáveis no JSON, página fora do intervalo.
- Para cada endpoint: caso de aceite → teste de feature → implementação.
- **Testes**: feature com PostgreSQL real; contrato (resposta × OpenAPI); **orçamento de queries**
  (falha se passar do número da Fase 1); `preventLazyLoading` ligado.

**Critério de aceite**: casos de aceite passam local e no deploy real; G3 (paridade v1×`php-laravel`) nulo para
estas rotas; orçamento respeitado; evidência em `execucao-fase-3.log`; PR mergeado.

---

### Fase 4 — Autenticação (GitHub OAuth + JWT)

- Fluxo OAuth com **`state`** (proteção contra CSRF) e tratamento de erro do GitHub; troca do `code`;
  `upsert` do `Dev`. Biblioteca (Socialite ou implementação direta) decidida em ADR **depois de testar**
  que o contrato do v1 é preservado.
- JWT com o **mesmo payload do contrato** (`{ username }`) e `Authorization: Bearer`; **nunca** token em
  query string; expiração definida e documentada; segredo no SSM.
- Guards/middleware equivalentes a `RequiredAuthFilter` e `OptionalAuthFilter` do v1. **Só Bearer** com `{username}`;
  o cookie `httpOnly` do original está **fora do escopo** (decisão de 2026-10-02) e vira limitação no README.
  Token válido de Dev inexistente: 401 em rota obrigatória, anônimo em rota opcional (divergência D-2).
- O GitHub fica atrás de uma interface (`GithubClient`), ligada no ServiceProvider; os testes usam `Http::fake`.
- `rate limiting` nas rotas de autenticação, **com o store definido no S7** e teste que prova o 429; CORS restrito (origem definida na spec, não `*`).
- Teste de segurança: token adulterado, expirado, ausente, de outro usuário.

**Parte automática × manual**: o fluxo é testado no CI com `GithubClient` falso (`Http::fake`); o login real é
**manual** (OAuth App do usuário) e a evidência é a saída versionada do `.http`, sem token nem segredo.

**Critério de aceite**: login real ponta a ponta com um OAuth App do GitHub (registrado pelo usuário);
`GET /me` retorna o Dev; casos de aceite de auth passam local e no real; G3 nulo; PR mergeado.

---

### Fase 5 — Endpoints autenticados de escrita e relacionamento

`POST /devs`, `POST /channels`, `POST /video`, os pares simétricos like/dislike/follow/ignore (sempre
**os dois lados do par juntos**, como no `devfinder-api`), `GET /likes/devs`, `GET /dislikes/devs` e
`GET /feed/subscriptions` (**sem rota no v1**, oráculo: original e contrato). Mapa completo das 30 operações
por fase em `specs/arquitetura-alvo.md`.

- **Policies** para autorização (dono do recurso); transações onde houver mais de uma escrita.
- Actions de escrita (`StoreVideo`, `StoreChannel`, `LikeDev`…) com **exceções tipadas** (`ChannelNotFound`,
  `VideoAlreadyExists`…), renderizadas no handler global **com o formato de erro do contrato**; nenhum corpo de
  erro montado no controller. A criação do `Dev` em `POST /channels` não chama o GitHub no controller: vai para
  uma Action que usa a interface do cliente.
- **Idempotência**: curtir duas vezes não duplica linha nem devolve erro de unicidade sem tratamento
  (`ON CONFLICT`).
- Bugs herdados da auditoria da Fase 0 corrigidos **com teste que falha antes do conserto**.
- Teste: criar → listar → remover para cada par; tentativa sem token, com token de outro usuário.

**Critério de aceite**: casos de aceite cobrindo criar → listar → remover passam; sem duplicata; G3
nulo (ou divergência aprovada em ADR); evidência real; PR mergeado.

---

### Fase 6 — Ingestão em lote (agendada)

Reimplementar `*RefreshController`/`task.ts` como **comando Artisan** compartilhado entre o agendamento
e a rota manual (`POST /video/refresh`), sem duplicar lógica: a regra mora numa **Action** (`IngestVideos`) chamada pelo comando e pela rota (padrão do v1: `VideoIngestor`).

- **Agendamento**: EventBridge Scheduler invocando o Lambda (14 milhões de invocações por mês no Always
  Free, segundo `aws-pending__.md`).
- **Escopo**: vídeos; canal fora (decisão herdada do v1 e do `serverless`).
- **Resiliência** (lição do `transcript`): timeout, retry com backoff e respeito a `retry-after` para
  JSONBin e GitHub; falha definitiva **registrada**; execução **idempotente** (rodar duas vezes não
  duplica).
- **Paridade com fixture**: o JSONBin muda com o tempo, então a comparação com o v1 usa uma **fixture
  congelada** do bin (versionada, sem dado real de terceiros) servida por um fake do cliente; o v1 recebe
  a mesma entrada. Contra o JSONBin real só se mede que o resumo mantém o shape.
- Mapeamento herdado: `channel_name` do bin → `channel` do contrato; resumo `videosAdded`/`videosFounded`/
  `errors` no mesmo shape.
- Cuidado com o limite de execução do Lambda e com as conexões ao Neon durante o lote.

**Critério de aceite**: resumo no mesmo shape e números iguais aos do v1 **para a mesma fixture**; execução
agendada comprovada no CloudWatch (não só "acho que disparou", lição do `serverless`); reexecução
idempotente; PR mergeado.

---

### Fase 7 — Observabilidade, desempenho, segurança e verificação real

- **7.1 Logs**: o formato JSON com id de requisição já existe desde a Fase 2a; aqui se **verifica** que
  nenhum dado sensível vaza (varredura nos logs do deploy) e a retenção curta.
- **7.2 Testes**: suíte completa no CI a cada PR (feature, contrato, orçamento de queries); e2e de fumaça
  contra o deploy real (`httpyac`, como no v1) com a saída versionada.
- **7.3 Desempenho**: carga leve (k6 ou autocannon) com os mesmos endpoints do v1 (`GET /devs` e
  `GET /feed/trending`), **comparando com os números do v1 só com a ressalva** de ambiente diferente
  (Lambda a frio/quente vs. PHP-FPM sempre ativo) — mesma cautela que o v1 registrou.
- **7.4 Segurança**: checklist da API (autenticação quebrada, exposição de dados, falta de limite de
  recursos, configuração insegura), `composer audit`, varredura de segredos; `APP_DEBUG=false`
  e CORS já são asserções do smoke test desde a Fase 2b, aqui só se reconfirmam.
- **7.5 Cold start** medido e **publicado** (Lambda frio, Neon frio, os dois frios).
- **7.6 Custo**: inventário completo (Lambda, CloudWatch, SSM, EventBridge, CloudFormation) e conferência
  no Cost Explorer.

**Critério de aceite**: gates G1, G2 e G3 verdes contra o deploy real; evidência versionada; PR mergeado.

---

### Fase 8 — Fechamento do `php-laravel`

Aplicar o checklist de prevenção de *long tail* de defeitos (skill `fechamento-versao`) ao fim do projeto:

- [ ] Rastreio de consumidores de cada mudança feita ao longo das fases; revisão holística do conjunto.
- [ ] README: demo funcionando, medições reais, **limitações listadas** pelo próprio autor,
      comparação com o v1 e com o `serverless` sem enganos.
- [ ] `specs/README.md`, `CLAUDE.md` e ADRs refletem o estado final; sem arquivo morto.
- [ ] **Fim de vida** (decidir até o dia 150 do Free Plan): upgrade para o plano pago mantendo só o que é
      Always Free, ou **teardown** pelo `specs/teardown.md` (nomes e ordem explícitos, sem curingas;
      conferir depois com o inventário somente leitura).
- [ ] Segredos do período rotacionados ou revogados; nada em notas locais.
- [ ] Decisão sobre o repositório remoto e sobre manter o v1 acessível.

**Critério de aceite**: checklist completo; PR mergeado; versão `1.0.0` marcada.

---

## 8. Estratégia de testes

| Camada | O que cobre | Ferramenta (a confirmar na Fase 0) |
|---|---|---|
| Unidade | **Actions** com dependências injetadas e fakes das integrações (`Http::fake`); regras e normalizações | Pest |
| Feature | HTTP real contra PostgreSQL de serviço no CI | Pest + Laravel |
| Contrato | Toda resposta × `fase-0-openapi.yaml` | Spectator, `laravel-openapi-validator` ou Gesso |
| Arquitetura | Regras de dependência da seção 6 (G4) | Pest `arch()` ou PHPat (Fase 0) |
| Mutação (opcional) | Qualidade dos testes das Actions | Infection |
| Orçamento de queries | Número máximo de queries por endpoint | Contador no teste (evento de query do Laravel) |
| Diferencial v1×`php-laravel` | Mesmos `.http`, respostas comparadas após normalizar ids e datas | `httpyac` + script de diff |
| Fumaça no real | `/health`, `/health/db` e um endpoint com dado | `httpyac` com saída versionada |
| Carga leve | Latência por endpoint | k6 ou autocannon |

Regra herdada do v1: **aceite se prova rodando os `.http` de verdade e guardando a saída**, não com
alegação em prosa.

Meta de cobertura da lógica de negócio definida em ADR (referência da comunidade: 70% ou mais), medida
com PCOV: o v1 mostrou que sem driver de cobertura o PHPUnit saía com código 1.

---

## 9. Custo e governança

| Serviço | Papel | Custo esperado | Guardrail |
|---|---|---|---|
| Lambda | API e ingestão | Always Free: 1 M requisições e 400.000 GB-s por mês | Concorrência reservada baixa; alarme de invocações |
| Function URL | Entrada HTTP | sem adicional (hipótese D4) | Rate limiting na aplicação |
| SSM Parameter Store (Standard) | Segredos | Always Free | — |
| EventBridge Scheduler | Ingestão agendada | Always Free (14 M/mês) | — |
| CloudWatch Logs | Logs | cota gratuita mensal | Retenção de 7 a 14 dias |
| Neon (externo) | PostgreSQL | plano gratuito (limites no ADR D2) | URL pooled no runtime; teto de conexões |
| GitHub Actions | CI/CD | cota do plano (repositório privado) | Cancelar execuções antigas por PR |

**Dentro da allowlist (e só estes)**: Lambda, Function URL, CloudWatch Logs, SSM Parameter Store Standard,
EventBridge Scheduler, IAM, CloudFormation e o **bucket S3 de artefatos do Serverless** (confirmado no S5).

**Fora da allowlist (o CI falha se aparecerem)**: RDS/Aurora, NAT Gateway, API Gateway não previsto,
Secrets Manager (cobra taxa fixa mesmo parado), ElastiCache, instâncias EC2, qualquer recurso em VPC.

**Disciplina**: revisar a lista de recursos de `serverless package` antes do primeiro deploy; rodar o
inventário somente leitura no fim de cada fase; **parar e investigar** ao menor sinal de cobrança
(o `fly launch` mostrou que um recurso pago pode nascer em silêncio).

---

## 10. Riscos e trade-offs

| Risco | Efeito | Mitigação | Detecção |
|---|---|---|---|
| Free Plan encerra a conta em 6 meses | API some | Plano de fim de vida (Fase 8); decisão até o dia 150 | Data no `README` e alerta |
| Cold start de Lambda PHP + Neon | Primeira requisição lenta (o revisor percebe) | G1, medição publicada, boot enxuto | Spike S3 e Fase 7 |
| Neon: horas de computação e conexões do plano gratuito | Estouro de limite ou erro de conexão | Pooled no runtime, concorrência reservada, sem ping contínuo | S3/S4 |
| Sem API Gateway/WAF | Abuso gasta crédito e conexões | Concorrência reservada, rate limiting, alarme | S6 |
| Pooler do Neon com migration ou prepared statements | Falha de migration ou erro intermitente | Migration pela URL direta; teste do S4 | S4 e CI |
| `config:cache` no Lambda | Leitura de `env()` fora de `config/` quebra | Regra de revisão 14 + teste | Larastan/revisão |
| Dump real com nomes de terceiros | Risco de marca e LGPD | Mantido fora do Git, como no v1; seed sintético versionado | Varredura no CI |
| Oráculo do v1 indisponível | G3 sem referência | Rodar o v1 pelo commit de referência em Docker | Fase 0 |
| Escopo crescer (auditoria, ferramentas) | Fase 0 interminável | Limitar às rotas do OpenAPI; ADR para o resto | Revisão do PR |
| Rate limiting sem store compartilhado (cache `array`) | Limite nunca dispara; abuso gasta crédito e conexões | Store decidido no S7 e teste do 429 | S7 e Fase 4 |
| Conta sem permissão para concorrência reservada | Sem teto contra abuso | Alternativa definida no S6; limitação no README | S6 |
| Spikes tardios invalidarem D1/D2 depois da Fase 1 | Migrations refeitas | Spike inicial S1–S3 na Fase 0 (G0) | Fase 0 |
| Versões (Laravel, Bref, camadas PHP) mudarem | Build quebra | Versões fixadas em ADR; `composer.lock` versionado | CI |

---

## 11. Decisões em aberto (para o usuário decidir antes de iniciar a Fase 0)

1. **Repositório remoto**: nome sugerido `devfinder-laravel` (privado), criado só no início da Fase 0.
   *(Decidido pelo usuário: projeto novo em `php-laravel/`, o `php-codei` fica intocado.)*
2. **Versão do Laravel e do PHP**: fixar a estável vigente no ADR D1. A pesquisa confirmou a camada Bref
   `php-83-fpm`; PHP 8.4 ou mais novo (preferível, o 8.3 já saiu do suporte ativo) **não foi verificado**:
   resolver no spike inicial da Fase 0, **antes** da ADR D1.
3. **Ingresso HTTP**: manter a hipótese Function URL ou já aceitar o HTTP API (US$ 1 por milhão,
   descontado do crédito)? Decidido no S6, com a sua aprovação.
4. **Oráculo do v1**: o deploy do Render e o banco (TiDB Cloud) ainda existem? Se não, o v1 roda pelo commit de referência.
5. **Seed**: usar o dump real (gitignored, como no v1) nos testes de carga e de paridade?
6. **OAuth App do GitHub**: registrar um novo para a URL da Lambda (a callback muda).
7. **DTO**: classes `readonly` próprias (padrão proposto) ou `spatie/laravel-data`?
8. **Teste de arquitetura**: Pest `arch()` ou PHPat? Decidir com um exemplo real na Fase 0.
9. **Nível do Larastan**: meta 9 sem baseline global; calibrar na Fase 2 e registrar exceções.
10. **UI de `/docs`**: qual usar, e se o Scramble entra como detector de divergência (D7).
11. ~~Documentos `__`~~: **decidido** (2026-10-02): ficam locais, não versionados, sem links no plano.
12. **Região** do Lambda e do Neon (mesma; qual) e **store do rate limiting** (S7).

## Ordem de execução resumida

```
Fase 0 (specs, ADRs, oráculo, spike inicial S1–S3)  →  Fase 1 (modelo + orçamentos)  →  Fase 2a (local) → 2b (nuvem, G0)
   →  Fase 3 (leitura pública)  →  Fase 4 (auth)  →  Fase 5 (escrita)  →  Fase 6 (ingestão)
   →  Fase 7 (observabilidade, desempenho, segurança, real)  →  Fase 8 (fechamento, fim de vida)
```

Cada seta só é cruzada com a spec da fase seguinte escrita e revisada, **e** o PR da fase anterior
mergeado. A spec é o artefato que se aprova, o PR é o artefato que se revisa, o código é a
consequência de ambos.

---

## Fontes

**Do próprio workspace (evidência de incidentes reais)**: `php-codei/CLAUDE.md`,
`specs/fase-7-observabilidade-testes.md`, `specs/deploy/fase-7-deploy-render.md`,
`specs/fase-5-escrita-relacionamentos.md`; `reactjs/criterios-revisor-senior-2026.md` e
`reactjs/CLAUDE.md`; `serverless/specs/aws-pending__.md` e `serverless/CLAUDE.md`; projeto `transcript`
(`docs/prompts-desenvolvimento/tokens__.md`, `fly-launch-fix001__.md`, `fly.io-destroy__.md`).

**Pesquisa na web de 2026-10-02 (blogs e documentação de terceiros; nada foi testado)**:

- [Bref](https://bref.sh/), [Laravel no Bref](https://bref.sh/docs/frameworks/laravel), [Storage no Lambda](https://bref.sh/docs/environment/storage), [Banco de dados no Bref](https://bref.sh/docs/environment/database)
- [Otimizando cold start do Laravel no Lambda](https://mnapoli.fr/optimizing-laravel-aws-lambda)
- [CodeIgniter 4 serverless](https://michalsn.dev/posts/serverless-codeigniter-4/) e [exemplo Laravel/CodeIgniter](https://github.com/canopas/serverless-php-example)
- [Function URLs vs API Gateway](https://theburningmonk.com/2024/03/when-to-use-api-gateway-vs-lambda-function-urls/) e [Function URLs com Serverless Framework](https://www.serverless.com/blog/aws-lambda-function-urls-with-serverless-framework)
- [Neon com Laravel](https://neon.com/docs/guides/laravel), [erros de conexão e SNI no Neon](https://neon.com/docs/connect/connection-errors)
- [Lambda e RDS: NAT e RDS Proxy](https://lumigo.io/aws-lambda-deployment/lambda-rds/)
- Contrato de API em Laravel: [Spectator](https://laravel-news.com/test-your-openapi-implementation-with-spectator), [laravel-openapi-validator](https://github.com/kirschbaum-development/laravel-openapi-validator), [Gesso](https://github.com/studio-design/gesso)
- Padrão de comunidade e arquitetura: [Vertical Slice com DDD no Laravel](https://vans.dev/blog/2025-08-04-beyond-mvc-laravel-applications-with-ddd-and-vsa/), [Spatie laravel-data](https://fperdomo.dev/how-to-structure-laravel-api-with-spatie-data), [Repository no Laravel: alternativas](https://medium.com/studocu-techblog/you-might-not-need-a-repository-in-laravel-3-alternatives-c241638a3922), [boas práticas de Laravel em 2026](https://buttercms.com/blog/laravel-best-practices/)
- Padrões PHP: [PER Coding Style](https://www.php-fig.org/per/coding-style/), [PHPStan nível 9](https://kokil.com.np/blog/php-static-analysis-with-phpstan-level-9), [ferramentas de análise de PHP](https://diffchecker.pro/blog/php-code-analysis/), [Scramble vs Swagger](https://mycuriosity.blog/scramble-vs-swagger-laravel-api-documentation-tools-compared)
- Revisão de Laravel: [N+1 e `preventLazyLoading`](https://ashallendesign.co.uk/blog/how-to-force-eager-loading-and-prevent-n-1-issues-in-laravel), [lista de revisão de código Laravel](https://redwerk.es/blog/lista-verificacion-revision-codigo-laravel/), [ferramentas de qualidade (Pint, Larastan, Pest)](https://soubiran.dev/series/empower-and-dynamize-our-vitepress-blog-with-a-laravel-api/pint-larastan-rector-and-pest-essential-for-success)
- A análise do plano da conta em `serverless/specs/aws-pending__.md`
