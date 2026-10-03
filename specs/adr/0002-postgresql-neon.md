# 0002 — PostgreSQL no Neon

- **Status**: **aceita** pelo usuário em 2026-10-03 (evidência: `specs/spikes/s2-pdo-pgsql-neon.md`, `s3-cold-start.md`, `s4-pooler-conexoes.md`)
- **Contexto**: precisa de banco relacional gratuito, acessível de fora de VPC (sem NAT nem RDS).
- **Decisão**: PostgreSQL no Neon (plano gratuito). URL **pooled** no runtime e URL **direta** nas migrations.
- **Alternativas**: Supabase (fallback); RDS (gasta crédito); MySQL/TiDB (o do v1).
- **Custo**: US$ 0; limites de computação e de conexões do plano gratuito (conferir os números atuais no S3/S4).
- **Critério de reversão**: S2 (conexão), S3 (cold start) ou S4 (pooler) falharem.
- **Região**: o projeto Neon fica na **mesma região AWS** do Lambda (ADR 0009).
