# specs — estado dos artefatos

Metodologia: ver [`../plan.md`](../plan.md). Spec antes do código; um PR por fase.

| Artefato | Estado |
|---|---|
| [`fase-0-especificacao.md`](./fase-0-especificacao.md) | em execução (2026-10-02): achados, decisões do usuário e pendências |
| [`fase-0-openapi.yaml`](./fase-0-openapi.yaml) | copiado do v1 e ajustado; revalidado contra o v1 (strict); campos `nullable` e exemplos sintéticos aplicados |
| [`acceptance/`](./acceptance/) | 9 `.http` por feature (62 requisições, 19 arquivos do v1 condensados + `app` e `search` novos); ambientes `v1`, `local`, `real`; sem `*.private.*` |
| [`adr/`](./adr/README.md) | 13 ADRs: 12 aceitas (0001–0003, 0005–0013), 0004 `proposta` |
| [`erros-v1.md`](./erros-v1.md) | inventário de erros do v1 (lido do código, não executado) |
| [`auditoria-original.md`](./auditoria-original.md) | 15 achados + registro de divergências (D-1 a D-11 aprovadas) |
| [`arquitetura-alvo.md`](./arquitetura-alvo.md) | estrutura, regras e mapa das 30 operações por fase |
| [`oraculo-v1.md`](./oraculo-v1.md) | como usar o v1 como referência; cobre 27 das 30 operações |
| [`dataset-de-paridade.md`](./dataset-de-paridade.md) | fixture sintética (35 devs, 3 canais, 55 vídeos) e regras do G3 |
| [`ferramentas-contexto.md`](./ferramentas-contexto.md) | Context7 (cobertura por API pública) e skills de Laravel (nada instalado) |
| [`tools/validate-contract.cjs`](./tools/validate-contract.cjs) | validador estrito do contrato contra uma API real; saída em `acceptance/validacao-contrato-v1.log` |
| [`spikes/`](./spikes/s1-laravel-bref.md) | resultados medidos do spike S1–S4 e S7 (S5 e S6 parciais) e `2a-ferramentas.md` (contrato, arquitetura, DTO, Scramble), 2026-10-03 |
| [`fase-1-modelo-de-dados.md`](./fase-1-modelo-de-dados.md) | **aprovada** (2026-10-03), ADR 0012 aceita; evidência em `fase-1-validacao.sql`/`.log`; migrations em `../database/migrations/` |
| [`fase-2a-esqueleto-local.md`](./fase-2a-esqueleto-local.md) | spec da Fase 2a; evidência em `execucao-fase-2a.log` e `spikes/2a-ferramentas.md` (Gesso, Pest `arch()`, DTO `readonly`, Scramble) |
| [`fase-2b-esqueleto-nuvem.md`](./fase-2b-esqueleto-nuvem.md) | spec da Fase 2b; evidência local em `execucao-fase-2b.log` e `spikes/s5-s6-fase-2b.md`; `teardown.md` (rascunho) e `iam/` (política da role de deploy, **ainda não validada por deploy**) |
| [`fase-3-leitura-publica.md`](./fase-3-leitura-publica.md) | spec da Fase 3 (decisões F3-1 a F3-10); evidência local em `execucao-fase-3.log`; G3 nulo nas rotas 1 a 9 |
| [`fase-4-autenticacao.md`](./fase-4-autenticacao.md) | spec da Fase 4 (decisões F4-1 a F4-12); ADR 0013 **aceita** (2026-10-04); divergências D-12 e D-13 aprovadas (2026-10-04); evidência local em `execucao-fase-4.log` |
| [`fase-5-escrita.md`](./fase-5-escrita.md) | spec da Fase 5 (decisões F5-1 a F5-14; F5-1 e F5-2 são do usuário); divergência D-14 proposta; evidência local em `execucao-fase-5.log` |
| [`fase-6-ingestao.md`](./fase-6-ingestao.md) | spec da Fase 6 (decisões F6-1 a F6-11); ADR 0014 `proposta`; divergência D-17 proposta; evidência local em `execucao-fase-6.log` |
| `fase-7-*.md` em diante | não escritos |
