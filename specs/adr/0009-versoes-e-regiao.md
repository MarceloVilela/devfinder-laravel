# 0009 — Versões (PHP, Laravel, Bref) e região

- **Status**: **aceita** pelo usuário em 2026-10-03 (evidência do spike S1–S4 em `specs/spikes/`)
- **Contexto**: o PHP 8.3 já saiu do suporte ativo; o Bref 3 tem camada para PHP 8.5, 8.4, 8.3 e 8.2; o projeto Neon já existe em
  `aws-us-east-2`.
- **Decisão**:
  - **PHP 8.4** (camada Bref `php-84`, versão 23 em `us-east-2`); 8.5 existe (`php-85`, versão 20) mas é mais nova e não foi testada.
  - **Laravel 13.x** (13.34.0 no spike; exige PHP `^8.3`), **Bref 3.0.x** com `bref/laravel-bridge` 3.1.x, deploy por **`osls`**.
  - **Região AWS `us-east-2`**, a mesma do projeto Neon (`aws-us-east-2`), PostgreSQL 18.
  - Alvos de latência no servidor (S3): quente com banco p95 ≤ 100 ms; Lambda frio ≤ 1,5 s; Lambda e Neon frios ≤ 2,5 s.
- **Evidência**: `s1-laravel-bref.md` (sobe, 16,5 MB, `pdo_pgsql`), `s2-pdo-pgsql-neon.md` (libpq 18.4, SNI e prepared statements OK),
  `s3-cold-start.md` (init ~1,0–1,2 s; resume do Neon ~0,6 s), `s4-pooler-conexoes.md` (migrations e carga leve sem erro); rate limiting: ADR 0011.
- **Alternativas**: PHP 8.5 (mais novo, menos testado); PHP 8.3 (suporte só de segurança); região `us-east-1` (a do AWS CLI e do
  projeto irmão; o Neon teria de ser recriado).
- **Medido no deploy real (2026-10-03, `spikes/g1-latencia-deploy-real.md`)**: quente com banco p95 51 ms (meta 100 ms: passa); Lambda e Neon frios 2,14 a 2,17 s (meta 2,5 s: passa); Lambda frio sem banco 1,64 s (meta 1,5 s: **fora por 0,14 s**, pendência da Fase 7).
- **Custo/risco**: a conta tem limite de concorrência 10 e **não permite reservar** (S6); a função usa `x86_64` (a `arm64` não foi testada).
- **Critério de reversão**: camada ou extensão `pdo_pgsql` ausente em uma atualização do Bref, ou cold start fora dos alvos.
