# ADRs do `php-laravel`

Formato: **Status** (`proposta` → `aceita` → `substituída`), Contexto, Decisão, Alternativas, Custo e
**Critério de reversão**. Nenhuma ADR vira `aceita` antes de a evidência que ela cita existir
(spike medido, teste real) e da aprovação do usuário. Aceitas em 2026-10-03: 0001, 0002, 0003, 0009, 0010 e 0011. Seguem `proposta` por decisão do usuário (2026-10-03): 0004, aceita na Fase 2b (evidência parcial do S6), e 0005 a 0008, aceitas na Fase 2a (precisam do esqueleto).

| ADR | Tema | Status |
|---|---|---|
| [0001](./0001-laravel.md) | Laravel e PHP | **aceita** (2026-10-03) |
| [0002](./0002-postgresql-neon.md) | PostgreSQL no Neon | **aceita** (2026-10-03) |
| [0003](./0003-lambda-bref.md) | AWS Lambda + Bref | **aceita** (2026-10-03) |
| [0004](./0004-entrada-http.md) | Entrada HTTP (Function URL) | proposta |
| [0005](./0005-organizacao-por-feature.md) | Organização por feature e Actions | proposta |
| [0006](./0006-sem-repository.md) | Sem Repository genérico | proposta |
| [0007](./0007-documentacao-contrato.md) | Documentação e teste de contrato | proposta |
| [0008](./0008-erros-tipados.md) | Erros tipados e formato do contrato | proposta |
| [0009](./0009-versoes-e-regiao.md) | Versões (PHP, Laravel, Bref) e região | **aceita** (2026-10-03) |
| [0010](./0010-custo-free-plan.md) | Regra de custo reformulada | **aceita** (2026-10-03) |
| [0011](./0011-rate-limiting-store.md) | Store do rate limiting | **aceita** (2026-10-03) |
