# S2 — `pdo_pgsql` da camada Bref conecta no Neon? (2026-10-03)

**Resultado: PASSA**, pela URL pooled e pela direta, com `sslmode=require`.

| Item | Medido |
|---|---|
| Servidor | PostgreSQL **18.6** (projeto Neon em `aws-us-east-2`, a mesma região do Lambda) |
| Cliente | libpq **18.4** na camada Bref (≥ 14: SNI funciona, **sem** `options=endpoint=...`) |
| Conexão | `pgsql` (pooled, host `-pooler`) e `pgsql_direct` (direta): ambas 200 |
| Prepared statements | OK pelo pooler (`PDO::ATTR_EMULATE_PREPARES = false`, `select ?::int, ?::text`) |
| `SET` de sessão | OK (`set application_name`) |
| Tempo de conexão (Lambda quente) | 25–40 ms; consulta trivial 3–6 ms |
| 1ª conexão após suspensão do compute | **~620 ms** de conexão (inclui o resume do Neon) |

**Notas**
- `channel_binding=require` da URL do Neon é **ignorado** pelo Laravel (não é opção do DSN); o que vale é `sslmode=require`.
- Um `SET` de sessão "passa" pelo pooler (modo transação) mas **não persiste** entre requisições: não depender dele.
- Compute do Neon: autoscaling 0,25–2 CU, suspende sozinho **5 min 16 s** depois da última atividade (registro de operações).
