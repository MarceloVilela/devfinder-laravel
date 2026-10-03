# G1 — latência medida contra o deploy real (2026-10-03)

> `devfinder-laravel-prod-web`: Bref `php-84-fpm`, 1024 MB, `x86_64`, `us-east-2`, Function URL; banco: Neon `aws-us-east-2` (PostgreSQL 18.6), URL **pooled**,
> segredos pelo SSM (`bref-ssm:`). Cliente em Marcelo (Brasil): o tempo do cliente inclui rede e TLS até Ohio, por isso o **servidor** (linha `REPORT` do
> CloudWatch) é a medida que vale para as metas da ADR 0009. Saída bruta: `execucao-fase-2b.log`, seção 14.

| Cenário | Meta (ADR 0009) | Medido no servidor | Medido no cliente | Resultado |
|---|---|---|---|---|
| **Quente, com banco** (`/health/db`, 55 chamadas) | p95 ≤ 100 ms | p50 ≈ 33 ms, p95 ≈ 51 ms, máx 55 ms | p50 577 ms, p95 666 ms | **passa** |
| Quente, sem banco (`/health`, `/v1`) | (sem meta) | ≈ 7 ms | p50 534–538 ms | n/a |
| **Lambda frio, sem banco** | ≤ 1,5 s | Init 1.362 ms + Duration 280 ms = **1,64 s** | 2,75 s | **não passa** (por 0,14 s) |
| **Lambda frio e Neon frio** (`/health/db`) | ≤ 2,5 s | Init 1.240 ms + Duration 934 ms = **2,17 s**; repetido após 25 min: 1.245 + 897 = **2,14 s** | 3,49 s e 3,26 s | **passa** |

Leituras:
- **A "meta" do cliente não existe**: 500 ms é rede e TLS (curl abre conexão nova a cada requisição). O cliente em São Paulo ou Ohio mediria bem menos; o número útil é o do servidor.
- **O Lambda não fica quente por 7 minutos**: a rodada "Neon frio, Lambda quente" na verdade pegou o Lambda também frio (Init no CloudWatch). Para separar os dois efeitos seria preciso manter o Lambda vivo, o que gastaria o limite de horas do Neon. Não foi feito.
- **Custo do Neon frio**: Duration 280 ms (Lambda frio sem banco) contra ~900 ms (Lambda frio com banco): o resume do compute e a conexão somam ≈ 0,6 s no servidor, igual ao S3 da Fase 0.
- **Memória**: 146 MB usados de 1.024 MB. Menos memória reduziria o custo por 100 ms, mas diminuiria a CPU e alongaria o Init; não testado.
- **Tamanho**: pacote de 16,8 MB (93,5 MB descompactado), com `aws/aws-sdk-php` (76 MB) vindo do `bref/laravel-bridge`. O limite do Lambda é 250 MB.

## Por que o frio de 1,64 s passa raspando e o que fazer (não feito)
O Init de 1,36 s inclui o Bref carregar o runtime e o `bref/secrets-loader` ler 2 parâmetros do SSM; os 280 ms seguintes incluem o `config:cache` que o `bref/laravel-bridge` roda no cold start.
Candidatos a reduzir, em ordem de custo-benefício, **sem custo de AWS**: (1) remover o `aws/aws-sdk-php` do pacote (não é usado; só o `async-aws/ssm`); (2) OPcache e `preload` na camada; (3) medir `arm64` (a ADR 0009 registra que não foi testada). A ADR 0009 diz "cold start fora dos alvos" como critério de reversão: o desvio é de 9 % e **não** justifica trocar a stack; apertar fica para a Fase 7.

## Conclusão do G1
Quente: atende. Frio com Neon: atende. Frio sem banco: **fora por 0,14 s**, registrado como pendência da Fase 7 (a Fase 2b não tenta otimizar).
