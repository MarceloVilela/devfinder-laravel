# Fase 0 — Especificação, ADRs, oráculo v1 e critérios de revisão — Registro de execução

> Referência: [`../plan.md`](../plan.md), seção 7, Fase 0.
> Status: **em execução** (iniciada em 2026-10-02, branch local `fase-0-especificacao`, sem commit).
> Nada aqui foi medido contra AWS ou Neon: **o spike inicial S1–S3 ainda não rodou**, então toda ADR é `proposta`.

## Feito (verificável nos arquivos)

| Tarefa do plano | Artefato | Estado |
|---|---|---|
| Documentos do projeto | `../CLAUDE.md`, `../README.md`, `README.md`, `../.gitignore` (inclui `**/*__.*` e `*.private.*`) | feito |
| Contrato | `fase-0-openapi.yaml`, copiado do v1 (`3229c4c`); YAML válido (`npx js-yaml`); `servers` e referências à stack antiga ajustados; 30 operações e 15 schemas conferidos por script | feito, **falta revalidar contra o comportamento real do v1** |
| Casos de aceite | `acceptance/`: 9 `.http` por feature (os 19 do v1 condensados, 62 requisições preservadas, mais `app.http` e `search.http` escritos aqui), `README.md`, `http-client.env.json` com `v1`, `local`, `real`; `private.env` **não** copiado | feito |
| ADRs | `adr/0001` a `0010` (D1–D8 + versões/região + custo) | escritas como `proposta` |
| Contrato de erro | `erros-v1.md`: 11 linhas, lidas do código do v1 | feito (confirmação por execução pendente) |
| Auditoria do original | `auditoria-original.md`: 13 achados, registro de divergências (vazio) | feito (leitura de código, nada executado) |
| Arquitetura de código | `arquitetura-alvo.md`: estrutura, regras, mapa das 30 operações por fase, 1 caso de leitura e 1 de escrita no papel | feito |
| Oráculo | `oraculo-v1.md`: commit de referência confirmado; Render respondeu 200 em 2026-10-02 | feito (subida local do v1 pendente) |

## Achados que mudam o plano

1. **O v1 só implementa 27 das 30 operações** (`GET /search`, `GET /feed/subscriptions`, `POST /channels/refresh`
   dão 404, no código e no Render). O plano dizia "paridade com o v1 em toda operação". Correção: o G3 cobre 27;
   as outras 3 têm o `devfinder-api` e o contrato como oráculo. Ver `auditoria-original.md`, A1.
2. **O host não tem `php` nem `composer`**: tudo roda em Docker. A Fase 2a precisa de `docker compose` com a
   aplicação, não só o Postgres.
3. **O original autentica por cookie `httpOnly`**, e o v1 só aceita Bearer. É decisão de contrato do usuário (A4).
4. **O ambiente `real` do v1 grava em produção**: o oráculo de escrita é o v1 local.

## Decisões do usuário (2026-10-02)

1. `search` e `subscriptions` **entram**; `channels/refresh` **fica fora** (A1).
2. Só **Bearer** com `{username}`; cookie `httpOnly` fora do escopo, listado como limitação no README (A4).
3. `GET /description/category`: **preservar** a serialização atual (A10).
4. Divergências D-1 a D-9 **aprovadas** (`auditoria-original.md`): D-6 (A13, 404/502 em `POST /devs`), D-7 (ids como string), D-8 (`nullable` documentado no contrato) e D-9 (exemplos do OpenAPI sintéticos).
5. Documentos `__` **não são versionados** e nenhum arquivo versionado os referencia.

## Ainda sem autorização

- Fazer o commit inicial e o push (o remoto `origin` já existe e está configurado localmente: `MarceloVilela/devfinder-laravel`; **nenhum commit até o usuário pedir**).
- Rodar o spike (conta AWS e projeto Neon são do usuário).

## Pendente (não feito)

- [ ] **Spike inicial S1–S3** (Laravel + Bref no Lambda; `pdo_pgsql` no Neon; cold start). Exige AWS e Neon.
- [ ] Preencher a ADR 0009 (PHP, Laravel, Bref, região) com o resultado do spike.
- [x] Rodar os `.http` contra o v1 local: feito em 2026-10-02, `acceptance/execucao-v1-baseline.log` (status das 62
      requisições; só 2 divergem do esperado, as de `/feed/subscriptions`, rota inexistente no v1).
- [x] **Revalidar o contrato** contra o v1: feito em 2026-10-02 com `tools/validate-contract.cjs` em modo estrito
      (`acceptance/validacao-contrato-v1.log`, 27 requisições). Nenhuma propriedade ausente ou extra; divergências só em
      ids inteiros × string (A14), `null` sem `nullable` (A15) e as 2 rotas que o v1 não tem. **Resolvido**: D-8 aplicada
      no YAML (5 campos `nullable: true`, 2ª rodada sem nenhum null divergente) e D-7 decide que o `php-laravel` devolve
      string (o diff de ids some lá). Resta só 404 em `/search` e `/feed/subscriptions`, esperado no v1.
- [ ] Escolher e testar com exemplo real: DTO, teste de arquitetura (Pest `arch()` ou PHPat), ferramenta de contrato
      (uma entre Spectator, laravel-openapi-validator, Gesso), UI de `/docs` e Scramble. Precisam do esqueleto Laravel.
- [x] **Dataset de paridade** definido em `dataset-de-paridade.md` (35 devs, 3 canais, 3 tags, 55 vídeos, 4 reações,
      tudo sintético). O **seeder PHP** do `php-laravel` fica para a Fase 2a (precisa do esqueleto Laravel). O `.http`
      de ingestão foi reescrito sem dados reais de terceiros.
- [x] Avaliar Context7 e skills de Laravel: `ferramentas-contexto.md` (cobertura confirmada pela API pública; MCP e
      skills **não** instalados nem testados).
- [ ] Conferir toda referência cruzada de `CLAUDE.md`, `README.md` e `specs/README.md` contra os arquivos reais.
- [ ] Commit inicial em `main` e push (só a pedido; remoto já criado pelo usuário).

## Critério de aceite (de `plan.md`)

- [ ] Specs e ADRs aprovados pelo usuário (decisões 1 a 5 dadas; as ADRs seguem `proposta` até a evidência dos spikes).
- [ ] Spike inicial S1–S3 com resultado medido.
- [x] Toda operação **no escopo** com pelo menos um caso de aceite: 29 de 29, conferido por script em 2026-10-02 (as requisições dos `.http` contra o OpenAPI). A cobertura herdada do v1 era de 27 de 30: faltavam `GET /` e `GET /search` (casos escritos agora em `app.http` e `search.http`); `POST /channels/refresh` está fora do escopo, sem caso. A frase anterior deste registro ("as 30 estão nos `.http`") estava errada.
- [ ] Ferramenta de contrato escolhida com evidência.
- [ ] `CLAUDE.md` sem referência quebrada.
- [ ] Commit de referência do v1 registrado (**feito**: `3229c4c`).
- [ ] Repositório com `main` e remoto; dataset de paridade definido.
- [ ] PR `fase-0-especificacao → main` mergeado (só a pedido).
