# 0011 — Store do rate limiting

- **Status**: **aceita** pelo usuário em 2026-10-03 (evidência: `specs/spikes/s7-rate-limiting-store.md`)
- **Contexto**: sem API Gateway nem WAF (ADR 0004) e sem concorrência reservada (S6: limite da conta é 10), o rate limiting da aplicação
  é a defesa de aplicação disponível. O cache `array` não persiste entre requisições no Lambda (S7), então o limite nunca dispara.
- **Decisão**: `RateLimiter` do Laravel com **cache em tabela do Postgres** (`CACHE_LIMITER_STORE=database`, tabela `cache`, URL pooled),
  aplicado **apenas** em rotas de autenticação (login e callback OAuth) e de escrita sensível, com limites baixos por IP e por usuário.
  Teste automatizado que prova o 429 (a frio e a quente) entra na Fase 4.
- **Alternativas**: DynamoDB como store (fora da allowlist; exigiria ADR e novo recurso); limite só pela concorrência da conta (10);
  não limitar (descartado: login sem limite é sinal negativo de revisão).
- **Custo**: 3 a 6 queries e 35–90 ms por pedido limitado; cada pedido acorda o compute do Neon; contagem aproximada sob concorrência
  (7 de 5 no teste com 8 paralelos).
- **Critério de reversão**: horas de computação do Neon se esgotando por tráfego em rotas limitadas, ou latência das rotas de auth fora
  do alvo: migrar para DynamoDB por ADR, ou reduzir o escopo do limiter.
