# Cold start publicado (Fase 7.5)

> Medido em 2026-10-05 no deploy real (Lambda `web`, x86_64, 1024 MB, `us-east-2`, Neon `sa-east`/us). Só medido: nada foi otimizado (decisão F7-2).
> Método: rajada de **10 requisições paralelas** a `GET /health/db` força sandboxes novos; o servidor reporta `Init Duration` (só no frio) e `Duration` no `REPORT` do CloudWatch. O tempo do cliente inclui rede.

| Cenário | Amostras | Init | Duration | Total no servidor | Cliente (máx.) |
|---|---|---|---|---|---|
| **Lambda frio + Neon frio** (rajada 2, ociosidade ≥ 6 min) | 10 sandboxes | 1,19–1,43 s | 0,79–1,01 s | **2,16–2,25 s** | 4,04 s |
| **Lambda frio + Neon quente** (rajada 3) | 1 sandbox | 1,20 s | 0,35 s | **1,56 s** | 2,69 s |
| **Quente** (160 requisições sequenciais) | 140 `REPORT`, 0 frios | — | `/devs` p50 45 ms, p95 76 ms; `/feed/trending` p50 39 ms, p95 63 ms; `/search` p50 39 ms; `/channels` p50 141 ms, p95 173 ms | — | — |

## Leitura contra o G1

- Quente com banco p95 ≤ 100 ms: **passa** (`/devs` 76 ms, `/feed/trending` 63 ms). `/channels` (186 itens, sem paginação por contrato) tem p95 173 ms: **fora da meta**, limite conhecido (OWASP API4).
- Frio com Neon frio ≤ 2,5 s no servidor: **passa** (máx. 2,25 s). Ao cliente, com 10 sandboxes simultâneos, chega a 4 s.
- Frio sem banco ≤ 1,5 s: **continua pendente** (Init 1,2 s + 0,35 s = 1,56 s com banco quente). Candidatos de otimização, **não aplicados**: remover `aws/aws-sdk-php` do pacote (ganho esperado de centenas de ms no autoload), `arm64`, OPcache preload.
- Nota: o `Init` do Lambda não é cobrado em tempo de execução nesta conta (dentro do free tier), então o custo é latência, não dinheiro.

## Carga leve (local, ressalva de ambiente)

`specs/tools/load-light.cjs`, 200 requisições, concorrência 4, conexão nova por requisição, servidor embutido do PHP nos dois lados (v1 :8081, laravel :8082):

| Rota | v1 req/s, p50 | php-laravel req/s, p50 |
|---|---|---|
| `/devs` | 52,6 / 64 ms | 25,7 / 149 ms |
| `/feed/trending` | 201,8 / 17 ms | 27,4 / 140 ms |

Ressalva: o ambiente não é o do Lambda (um processo `php -S` por contêiner, dados reais nos dois). O Laravel é mais lento que o CodeIgniter em CPU local; no Lambda o número que vale é o do `REPORT`, acima. O `autocannon` não completa requisições contra `php -S` (0 respostas), por isso a ferramenta própria.
