# 0007 — Documentação e teste de contrato

- **Status**: proposta
- **Contexto**: o v1 tinha OpenAPI sem UI e sem teste; o contrato podia divergir em silêncio.
- **Decisão**: `specs/fase-0-openapi.yaml` é a fonte de verdade; UI em `/docs`; teste de contrato a cada PR
  a partir da Fase 3. Ferramenta de validação: **uma** entre Spectator, laravel-openapi-validator e Gesso,
  escolhida com teste real (pendente). Scramble só como detector de divergência, se funcionar no Lambda.
- **Alternativas**: gerar o contrato do código (Scramble) como fonte.
- **Custo**: manter o YAML sincronizado.
- **Critério de reversão**: ferramenta incompatível com Bref/Lambda; sobra só o teste de contrato.
