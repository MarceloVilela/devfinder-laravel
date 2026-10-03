# S3 — Quanto custa o frio de Lambda + Neon? (2026-10-03)

Tempos do **servidor** (linhas `REPORT` do CloudWatch) e do **cliente** (máquina do autor, no Brasil, com RTT até Ohio).

| Cenário | Servidor | Cliente |
|---|---|---|
| Lambda quente, sem banco (`/health`) | **9–12 ms** | 0,53–0,64 s |
| Lambda quente, com banco (`/health/db`) | 47–63 ms (conexão 25–40 ms) | ~0,62 s |
| **Lambda frio**, sem banco | init **1037 ms** + 236 ms = **1,27 s** (2ª amostra: 1127 + 242 = 1,37 s) | 2,55 s e 2,81 s |
| **Neon frio**, Lambda quente (`/health/db`) | 673 ms (conexão 625 ms) | 1,35 s |
| **Lambda e Neon frios** (`/health/db`) | init 1049 + 893 = **1,94 s** | 3,81 s |

- Init do Lambda em 8 cold starts: **897–1184 ms**.
- O resume do compute do Neon custa **~0,6 s** (conexão 625–627 ms contra 25–40 ms com o compute acordado).
- A diferença cliente × servidor (1,3 a 1,9 s a frio) é rede, DNS e TLS até `us-east-2`; vale só para este cliente.

**Contra as metas do G1 (plano):** quente p95 ≤ 500 ms, frio Lambda ≤ 5 s, os dois frios ≤ 10 s.
Frio e frio duplo **passam com folga**, no servidor e no cliente. O quente medido do cliente (0,5–0,6 s) fica no limite e é quase
todo rede; no servidor são ~10 ms (50 a 75 ms com banco).

**Proposta de alvo apertado (ADR 0009, a aprovar)**, medido no servidor: quente com banco p95 ≤ 100 ms (medido 75);
Lambda frio ≤ 1,5 s (medido 1,27–1,37); os dois frios ≤ 2,5 s (medido 1,94).

**Limites desta medição:** poucas amostras (1 a 3 por cenário frio); uma região; sem OPcache preload, sem `config:cache` e sem
`route:cache` (o spike usa o boot padrão do Laravel: há margem para reduzir o init).
