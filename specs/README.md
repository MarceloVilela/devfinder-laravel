# specs — estado dos artefatos

Metodologia: ver [`../plan.md`](../plan.md). Spec antes do código; um PR por fase.

| Artefato | Estado |
|---|---|
| [`fase-0-especificacao.md`](./fase-0-especificacao.md) | em execução (2026-10-02): achados, decisões do usuário e pendências |
| [`fase-0-openapi.yaml`](./fase-0-openapi.yaml) | copiado do v1 e ajustado; revalidado contra o v1 (strict); campos `nullable` e exemplos sintéticos aplicados |
| [`acceptance/`](./acceptance/) | 9 `.http` por feature (62 requisições, 19 arquivos do v1 condensados + `app` e `search` novos); ambientes `v1`, `local`, `real`; sem `*.private.*` |
| [`adr/`](./adr/README.md) | 11 ADRs, todas `proposta` (a 0009 pronta para aprovação) |
| [`erros-v1.md`](./erros-v1.md) | inventário de erros do v1 (lido do código, não executado) |
| [`auditoria-original.md`](./auditoria-original.md) | 15 achados + registro de divergências (D-1 a D-9 aprovadas) |
| [`arquitetura-alvo.md`](./arquitetura-alvo.md) | estrutura, regras e mapa das 30 operações por fase |
| [`oraculo-v1.md`](./oraculo-v1.md) | como usar o v1 como referência; cobre 27 das 30 operações |
| [`dataset-de-paridade.md`](./dataset-de-paridade.md) | fixture sintética (35 devs, 3 canais, 55 vídeos) e regras do G3 |
| [`ferramentas-contexto.md`](./ferramentas-contexto.md) | Context7 (cobertura por API pública) e skills de Laravel (nada instalado) |
| [`tools/validate-contract.cjs`](./tools/validate-contract.cjs) | validador estrito do contrato contra uma API real; saída em `acceptance/validacao-contrato-v1.log` |
| [`spikes/`](./spikes/s1-laravel-bref.md) | resultados medidos do spike S1–S4 e S7 (S5 e S6 parciais), 2026-10-03 |
| `fase-1-modelo-de-dados.md` em diante | não escritos |
