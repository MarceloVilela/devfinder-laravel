# 0014 — Agendamento da ingestão: regra agendada do EventBridge + função de console

- **Status**: **proposta** (2026-10-04); vira `aceita` com a execução agendada comprovada no CloudWatch e a aprovação do usuário (evidência: `specs/execucao-fase-6.log`).
- **Contexto**: o original roda a ingestão num GitHub Action (`video-refresh.yml`, `cron: '0 */12 * * *'`) que busca o bin do JSONBin e faz `POST /video/refresh`.
  O `serverless` trocou isso por uma função agendada que escreve direto no banco; o `php-codei` ficou com o Action. Aqui a conta tem concorrência 10, sem WAF,
  e a regra de custo (ADR 0010) exige um recurso novo na allowlist, sem custo.
- **Decisão**:
  1. **Uma Action** (`IngestVideos`) para o comando `video:refresh` e para `POST /video/refresh`.
  2. **Função `refresh`** própria (runtime `php-84-console` do Bref, 300 s, 1024 MB, sem URL), com os segredos do JSONBin só nela.
  3. **Regra do EventBridge** (`schedule` do Serverless, `rate(12 hours)`, entrada `"video:refresh"`), e não o EventBridge Scheduler do plano: a regra agendada não tem
     custo, vem pronta no Serverless e precisa de menos recursos (sem role de execução para o Scheduler). `AWS::Events::Rule` entra na allowlist, só com `ScheduleExpression`
     e sem `EventPattern`; a role de deploy ganha `events:*` limitado ao prefixo `devfinder-laravel-prod-`.
  4. **Resiliência** em `ResilientHttp` (3 tentativas, backoff 0,5 s dobrando, `Retry-After` até 10 s em 429, 502, 503 e 504 e erro de conexão), usada no JSONBin e no GitHub;
     fonte indisponível sai com código 1 e erro no log.
- **Alternativas**: GitHub Action chamando a API (obriga a manter um token de longa duração e a URL pública da função); EventBridge Scheduler (mais recursos, mesmo custo zero);
  agendar o `web` (o Lambda web tem 28 s e a ingestão pode levar minutos); Step Functions (fora da allowlist).
- **Custo**: 2 invocações por dia; a regra agendada é gratuita; a função `refresh` entra nos mesmos limites do Lambda. Cada execução acorda o Neon por alguns segundos.
- **Limites conhecidos**: a função tem 300 s; um bin muito grande exigiria dividir o lote. Sem concorrência reservada, uma execução lenta pode disputar as 10 execuções da conta com o tráfego.
- **Critério de reversão**: execução agendada que estoura os 300 s ou consome as horas de computação do Neon: reduzir a frequência, dividir o lote ou mover a ingestão para fora do Lambda por nova ADR.
