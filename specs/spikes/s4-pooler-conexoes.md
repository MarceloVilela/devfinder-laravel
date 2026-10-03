# S4 — Conexões: pooled e direta funcionam? O pooler tolera o que o Laravel usa? (2026-10-03, antecipado da Fase 2b)

**Resultado: PASSA**, em escala leve.

**Migrations** (`php artisan migrate` e `migrate:reset`, 3 migrations padrão do Laravel, rodadas de um container PHP 8.4 com
`pdo_pgsql` contra o Neon): funcionaram **pela URL pooled e pela direta**. A migration pelo pooler **não** falhou aqui. Mesmo assim
a regra do plano se mantém (migration pela direta), por segurança com `SET`, locks e transações longas. O banco ficou sem tabelas depois.

**Carga leve** (8 requisições simultâneas, 160 em `/health/db` pooled e 160 em `/health/db-direct`):

| Caminho | Respostas | Conexão no servidor (p50 / p95 / máx) |
|---|---|---|
| pooled | 160 × 200, **0 erros** | 25 / 79 / 97 ms |
| direta | 155 × 200 + 5 falhas **do cliente** (`Temporary failure in name resolution`, DNS local) | 30 / 43 / 85 ms |

Lado do servidor na janela do teste: **325 invocações, 0 erros nos logs, 8 cold starts** (um por instância simultânea), concorrência
máxima **8**, **0 throttles**, `Duration` p50 47 ms / p95 75 ms, memória máxima 135 MB.

**Limites:** 8 simultâneas é pouco (o limite da conta é 10, ver S6); não mediu o teto de conexões do plano gratuito do Neon nem
a carga sustentada. O resultado vale como "não há problema de compatibilidade", não como teste de capacidade.
