# Fase 7 — Observabilidade, desempenho, segurança e verificação contra o deploy real

> Referência: [`../plan.md`](../plan.md), seção 7, Fase 7. Branch `fase-7-observabilidade-desempenho`, a partir de `main` (PR #11 mergeado).
> Status: **implementada localmente, aguardando PR e deploy** (iniciada em 2026-10-05). É uma fase de **medir, verificar e documentar**: o usuário decidiu (2026-10-05) **não** criar recurso novo na
> conta (nada de alarmes ou SNS) e **não** mexer no pacote nem na arquitetura para o cold start; só medir e publicar. O aceite se prova em `execucao-fase-7.log`.

## Decisões do usuário (2026-10-05)

| # | Decisão | Consequência |
|---|---|---|
| F7-1 | **Sem alarmes nem SNS**: só medir e documentar a observabilidade | Nenhum recurso novo na allowlist; a lacuna "ninguém é avisado se a ingestão parar" fica registrada como limitação |
| F7-2 | **Cold start: só medir e publicar** (sem tirar o `aws/aws-sdk-php`, sem `arm64`) | A pendência G1 (frio sem banco 1,64 s contra 1,5 s) continua e é **medida de novo, com várias amostras**; os candidatos de otimização ficam registrados com o ganho esperado |

## Escopo (7.1 a 7.6 do plano) e o que cada item entrega

| Item | Entrega | Prova |
|---|---|---|
| **7.1 Logs** | Varredura dos logs do deploy (CloudWatch) atrás de dado sensível (JWT, `Bearer`, URL do banco, chaves, e-mail) e da retenção curta; teste automatizado de que os logs das rotas de erro e de login não carregam token, cookie nem segredo | varredura somente leitura + testes |
| **7.2 Testes** | Suíte completa no CI (já roda a cada PR) e **e2e de fumaça contra o deploy real** com `httpyac`, saída versionada. Só os casos que não gravam (leituras, `/docs`, 401 e 403 sem token); o e2e **autenticado** depende do login real e fica pendente | `execucao-fase-7.log` |
| **7.3 Desempenho** | Carga leve (`autocannon`) em `GET /devs` e `GET /feed/trending` no deploy real (concorrência baixa: a conta tem limite 10) e, com a **ressalva de ambiente diferente**, no v1 e no `php-laravel` locais; o número que vale no Lambda é o do servidor (`REPORT` do CloudWatch), não o do cliente | `execucao-fase-7.log` |
| **7.4 Segurança** | Checklist OWASP API Top 10 (2023) com a evidência de cada item (teste, gate ou limite conhecido); `composer audit`; **varredura de segredos** automática no CI; cabeçalhos básicos de resposta (`X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer`) | `specs/seguranca-owasp-api.md` + testes |
| **7.5 Cold start** | Medido e **publicado**: Lambda frio, Neon frio e os dois frios, `web` e `refresh`, várias amostras (rajada de requisições paralelas força sandboxes novos) | `specs/spikes/cold-start-publicado.md` |
| **7.6 Custo** | Inventário completo (`scripts/custo.sh`, somente leitura: Lambda, CloudWatch, SSM, EventBridge, CloudFormation, S3) e conferência no Cost Explorer e nos Budgets | `execucao-fase-7.log` |

## Gates contra o deploy real

| Gate | Como se prova nesta fase |
|---|---|
| **G1** latência | Quente com banco p95 ≤ 100 ms e frio com Neon ≤ 2,5 s (já passavam) e frio sem banco ≤ 1,5 s (pendente; medido de novo, sem otimizar) |
| **G2** custo | Inventário sem recurso fora da allowlist e US$ 0,00 no consumo bruto |
| **G3** paridade | (a) v1 local × `php-laravel` local **com o dump real** (escala real: 186 canais, 500 vídeos, 17 páginas de trending); (b) **deploy real × `php-laravel` local com os mesmos dados** (inclui os 39 vídeos que a ingestão trouxe). A (b) prova que Lambda e Neon se comportam como o ambiente local (ordenação, `norm_text`, paginação) |

## Fora do escopo

Alarmes, SNS, painéis (F7-1); mexer em pacote, OPcache ou arquitetura (F7-2); e2e autenticado no deploy real e login real (dependem do clique do usuário no GitHub);
escrever em produção para medir.

## Critério de aceite

- [ ] Varredura de logs do deploy sem dado sensível; testes de log verdes.
- [ ] e2e de fumaça contra o deploy real com saída versionada.
- [ ] Carga leve e cold start medidos e publicados, com as ressalvas.
- [ ] Checklist de segurança preenchido; varredura de segredos no CI; cabeçalhos básicos testados.
- [ ] Inventário de custo conferido; G1, G2 e G3 reportados (G1 com a pendência explícita, se continuar).
- [ ] PR mergeado.
