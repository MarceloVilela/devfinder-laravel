# S7 — Qual store faz o rate limiting funcionar entre instâncias do Lambda? (2026-10-03)

**Resultado: o cache `array` não funciona; o cache em tabela do Postgres funciona, com custo e imprecisão sob concorrência.**

Teste: `Illuminate\Cache\RateLimiter` (o mesmo que o middleware `throttle` usa) com limite de **5 por minuto** na mesma chave.

| Store | Sequencial (8 pedidos) | 24 pedidos, 8 em paralelo | Custo por pedido |
|---|---|---|---|
| `array` | 200 em todos; contador sempre 1 | **24 de 24 passam** | 0 queries, ~0,5 ms |
| `database` (tabela `cache` no Neon, URL pooled) | 200 ×5 e **429** do 6º em diante | **7 passam** (esperado 5), 17 recebem 429 | **6 queries** por pedido permitido (8 no primeiro, 3 quando bloqueia); **35–90 ms** no servidor |

**Leitura**
- `array` zera a cada requisição (o Laravel reinicia a aplicação por requisição no Bref/FPM): o limite **nunca dispara**.
- `database` cumpre o limite, mas a contagem é **aproximada** sob concorrência: `tooManyAttempts` e `hit` não são atômicos (passaram 7 de 5).
  Serve como proteção contra abuso, não como cota exata.
- Cada pedido limitado consome queries e **acorda o compute do Neon**. Um atacante que insista numa rota limitada mantém o banco acordado
  e gasta as horas de computação do plano gratuito. Por isso o limiter deve ficar **só nas rotas de autenticação e de escrita**, nunca
  global, e o limite de concorrência da conta (10) segue como o teto real (S6).
- Não testado: DynamoDB como store (fora da allowlist; exigiria ADR) e qualquer solução na borda (sem WAF nem API Gateway, por D4).

Ver ADR 0011.
