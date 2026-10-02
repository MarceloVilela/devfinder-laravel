# ADRs do `php-laravel`

Formato: **Status** (`proposta` → `aceita` → `substituída`), Contexto, Decisão, Alternativas, Custo e
**Critério de reversão**. Nenhuma ADR vira `aceita` antes de a evidência que ela cita existir
(spike medido, teste real). Hoje todas estão `proposta`: dependem do spike inicial S1–S3 e da
aprovação do usuário.

| ADR | Tema | Status |
|---|---|---|
| [0001](./0001-laravel.md) | Laravel e PHP | proposta |
| [0002](./0002-postgresql-neon.md) | PostgreSQL no Neon | proposta |
| [0003](./0003-lambda-bref.md) | AWS Lambda + Bref | proposta |
| [0004](./0004-entrada-http.md) | Entrada HTTP (Function URL) | proposta |
| [0005](./0005-organizacao-por-feature.md) | Organização por feature e Actions | proposta |
| [0006](./0006-sem-repository.md) | Sem Repository genérico | proposta |
| [0007](./0007-documentacao-contrato.md) | Documentação e teste de contrato | proposta |
| [0008](./0008-erros-tipados.md) | Erros tipados e formato do contrato | proposta |
| [0009](./0009-versoes-e-regiao.md) | Versões (PHP, Laravel, Bref) e região | proposta (bloqueada pelo spike) |
| [0010](./0010-custo-free-plan.md) | Regra de custo reformulada | proposta |
