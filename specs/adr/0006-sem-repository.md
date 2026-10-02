# 0006 — Sem Repository genérico

- **Status**: proposta
- **Contexto**: o Eloquent já é a camada de acesso; repositório genérico sobre ele é sinal de revisão negativo.
- **Decisão**: Eloquent com scopes e objetos de consulta em `Queries/`. Repositório só com ADR própria.
- **Alternativas**: Repository por entidade (o `transcript` usa; diverge de propósito).
- **Custo**: Actions dependem do Eloquent (testadas com banco real).
- **Critério de reversão**: necessidade real de trocar o armazenamento ou de testar regra sem banco.
