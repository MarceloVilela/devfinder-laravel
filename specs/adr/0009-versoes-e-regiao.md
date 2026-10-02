# 0009 — Versões (PHP, Laravel, Bref) e região

- **Status**: proposta, **bloqueada** pelo spike inicial S1–S3
- **Contexto**: a pesquisa confirmou a camada Bref `php-83-fpm`. PHP 8.4 ou mais novo é preferível (o 8.3
  já saiu do suporte ativo, a verificar), mas a camada Bref correspondente **não foi verificada**.
- **Decisão (a preencher)**: PHP ___, Laravel ___, camada Bref ___, região AWS ___ (a mesma do projeto Neon).
- **Evidência exigida**: `specs/spikes/s1-laravel-bref.md` (versões que subiram no Lambda) e `s3-cold-start.md`.
- **Critério de reversão**: camada ou extensão `pdo_pgsql` indisponível na versão escolhida.
