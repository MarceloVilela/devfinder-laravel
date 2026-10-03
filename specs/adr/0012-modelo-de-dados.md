# 0012 — Modelo de dados relacional

- **Status**: **aceita** pelo usuário em 2026-10-03 (evidência: `specs/fase-1-validacao.log`)
- **Contexto**: o original usa 3 coleções Mongo com arrays embutidos; o v1 normalizou em 7 tabelas MySQL. O alvo é PostgreSQL 18 (Neon, ADR 0002) com o contrato id-string (D-7).
- **Decisão**: 7 tabelas normalizadas (`devs`, `channels`, `tags`, `channel_tag`, `videos`, `dev_reactions`, `channel_reactions`), id **UUIDv7** (`uuidv7()` do PostgreSQL 18, decisão do usuário), string no JSON;
  `UNIQUE` parcial em `norm_text()` (`lower(unaccent())`, sem caixa nem acento) para `username`, `name` e `link`; reações com PK composta incluindo `type` (`ENUM` nativo) e `CHECK` de auto-reação; `timestamptz`; **soft delete (`deleted_at`) nas 4 tabelas de entidade, com índices únicos parciais `WHERE deleted_at IS NULL`** (decisão do usuário, 2026-10-03);
  paginação de 30 fixos com clamp igual ao v1 e os campos aditivos `page` e `totalPages` (D-11); busca `LIKE` escapado sobre `norm_text()` com índice GIN `pg_trgm`. Detalhes em `specs/fase-1-modelo-de-dados.md`.
- **Alternativas**: coluna `jsonb` para arrays (perde FK e idempotência por chave); `bigint` como id (descartada); `text` + `CHECK` no lugar do `ENUM`; só `lower()` (acento diferencia); `ILIKE` sem índice; full-text; sem soft delete (descartada pelo usuário).
- **Custo**: nenhum recurso novo. Toda consulta escrita à mão precisa filtrar `deleted_at IS NULL` (o Eloquent faz sozinho). Extensões `unaccent` e `pg_trgm` (suportadas no Neon) e 3 índices GIN (escrita um pouco mais cara). Consulta escrita à mão deve usar `norm_text()` dos dois lados. `ENUM` novo valor exige `ALTER TYPE`. Offset linear na paginação.
- **Critério de reversão**: custo de escrita ou de armazenamento dos GIN relevante no plano gratuito do Neon (voltar a `ILIKE` sem índice); extensão indisponível no Neon (trocar por `lower()`).
