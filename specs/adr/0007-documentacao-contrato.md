# 0007 — Documentação e teste de contrato

- **Status**: **aceita** pelo usuário em 2026-10-03 (evidência: `specs/spikes/2a-ferramentas.md`)
- **Contexto**: o v1 tinha OpenAPI sem UI e sem teste; o contrato podia divergir em silêncio.
- **Decisão**: `specs/fase-0-openapi.yaml` é a fonte de verdade; UI em `/docs`; teste de contrato a cada PR
  a partir da Fase 3. Ferramenta de validação: **Gesso** (dev-only), escolhida em teste real: das três candidatas foi a única que reprovou
  resposta com schema errado **e** rota fora do contrato e resolve a raiz `GET /`; `gesso:routes --fail-on-undocumented` é o gate
  de rotas fora do contrato no CI. `/docs` serve o YAML-fonte com Swagger UI. Scramble só como detector local (dev-only, fora do CI).
- **Alternativas**: gerar o contrato do código (Scramble) como fonte; Spectator (deixou passar rota fora do contrato em silêncio); laravel-openapi-validator (não valida o `GET /` sem editar o YAML).
- **Custo**: manter o YAML sincronizado; Gesso é projeto jovem (troca = um trait, o contrato continua sendo o YAML).
- **Critério de reversão**: ferramenta incompatível com Bref/Lambda; sobra só o teste de contrato.
