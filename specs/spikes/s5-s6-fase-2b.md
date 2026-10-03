# S5 e S6 na Fase 2b — o que foi medido sem tocar a conta e o que falta

> 2026-10-03. Continuação de `s5-s6-achados-parciais.md`. A 1ª metade (leitura e empacotamento local) não tocou a conta; a 2ª (seção "Ações na conta", no fim) foi feita depois da autorização do usuário.

## S5 — deploy sem chave de longa duração

| Pergunta | Resultado | Evidência |
|---|---|---|
| O `osls` exige login do Serverless Framework? | **Não** (já medido na Fase 0: dois deploys e duas remoções sem login) | `s5-s6-achados-parciais.md` |
| `osls package` precisa de credenciais da AWS? | **Não**, depois de trocar `${aws:accountId}` por `Fn::Join` + `Ref: AWS::AccountId` no `serverless.yml` (o id só o CloudFormation conhece). Com `${aws:accountId}` o empacotamento do PR falharia sem credenciais | `execucao-fase-2b.log`, seção 2 |
| Que recursos o template cria? | **9**, todos na allowlist: bucket de artefatos e a policy dele, log group (7 dias), role de execução, função, versão, Function URL e 2 permissões | `execucao-fase-2b.log`, seção 2 |
| Tamanho do pacote | 16,8 MB zip, 93,5 MB descompactado (limite do Lambda: 250 MB) | idem |
| Role de deploy com escopo mínimo | **Rascunhada** (`specs/iam/deploy-role.json` e `deploy-role-trust.json`), levantada **por leitura do template**, não por um deploy | pendente |
| OIDC GitHub→AWS funciona? | **Não testado**: não existe provedor OIDC na conta (conferido por `aws iam list-open-id-connect-providers`, vazio) | `scripts/aws-bootstrap.sh` (plano) |

Achados desta etapa:
- **`bref-ssm:` e não `${ssm:...}`.** O `${ssm:...}` do Serverless grava o valor em texto no template e na configuração da função; o `bref-ssm:` é resolvido pelo runtime no cold start (exige `bref/secrets-loader`). A allowlist reprova `base64:`, URL com senha e chave AWS em texto.
- **Não rodar `config:cache` no build.** O `bref/laravel-bridge` já cacheia a configuração no cold start, em `/tmp`, com as variáveis reais. Cachear no CI congelaria no pacote os valores do CI.
- **`config/gesso.php` quebrava a produção.** O arquivo publicado referenciava constantes de uma dependência dev-only; com `composer install --no-dev` o `package:discover` falhava (`Class "Studio\Gesso\OpenApiResponseValidator" not found`). Trocado por literais. O job `package` do CI (sem dependências de desenvolvimento) é a rede de proteção.
- **O `bref/laravel-bridge` registra `POST /signed-upload-url`**, uma rota não documentada de URL assinada para S3. Desligada por `config/bref.php` (`uploads.route = null`); o gate `gesso:routes --fail-on-undocumented` a pegou.
- **Orçamento já existe**: `Orcamento USD 0,01` (limite US$ 1,00 por mês, alerta quando o gasto passa de US$ 0,01). Ele **inclui créditos**, então só avisa quando o crédito acaba. `scripts/aws-bootstrap.sh` propõe um segundo orçamento sem créditos (consumo bruto), que ainda **não foi criado**.

## S6 — Function URL, concorrência e custo

| Pergunta | Resultado |
|---|---|
| Limite de concorrência da conta | **10**, sem reserva possível (reconferido hoje: `limite 10, semReserva 10`) |
| Defesa de infraestrutura contra abuso | **não existe** (sem WAF, sem throttling na Function URL, sem concorrência reservada). Sobram: o limite de 10 da conta (também teto de custo e de conexões no Neon), o rate limiting da aplicação (ADR 0011, Fase 4) e o alarme de custo |
| Custo | `scripts/custo.sh`: US$ 0,00 em todos os serviços de 26/09 a 03/10 (bruto, nanodólares de S3 e transferência); sem stack, função, log group nem bucket. **O Cost Explorer atrasa horas**: o inventário de recursos é a prova, não o valor |
| Consequência para a ADR 0004 | A decisão (Function URL) continua válida **com a ressalva documentada**: o abuso volumétrico não tem defesa de infraestrutura nesta conta. Isso vai para o README como limitação |

## Ações na conta (feitas em 2026-10-03, autorizadas pelo usuário) e o que ainda falta

**Feito** (`execucao-fase-2b.log`, seções 8 a 14):
1. `scripts/aws-bootstrap.sh --apply`: provedor OIDC, role `devfinder-laravel-github-deploy` e o 2º orçamento `devfinder-laravel-consumo-bruto` (sem créditos, mesmo destinatário do orçamento existente).
2. `app-key` e `db-url` (pooled) gravados no SSM como SecureString.
3. `migrate` no Neon (PostgreSQL 18.6): **`unaccent`, `pg_trgm` e `uuidv7()` funcionam**; `norm_text` e o unique por acento e caixa confirmados.
4. Primeiro deploy, feito **à mão com credenciais de administrador** (não pelo OIDC): stack de 9 recursos, todos na allowlist, log group com 7 dias.
5. Smoke test contra a Function URL: 21 checagens, incluindo a configuração viva da função (`APP_DEBUG=false`, segredos só como `bref-ssm:`).
6. Latência (G1): `g1-latencia-deploy-real.md`.

**Falta**
1. **OIDC de ponta a ponta**: só funciona a partir da `main` (a confiança da role é restrita a ela); precisa das variáveis `AWS_DEPLOY_ROLE_ARN`, `CORS_ALLOWED_ORIGINS` e do secret `DIRECT_DATABASE_URL` no GitHub, que **não foram criados**. Até lá o job `deploy` do `deploy.yml` é pulado (`if: vars.AWS_DEPLOY_ROLE_ARN != ''`).
2. **A política da role de deploy não foi validada**: o deploy desta etapa usou administrador. A política cobre o que o `osls` chama pelo que se leu do template e do CloudTrail (que atrasa); o 1º deploy pelo OIDC pode pedir ajustes (S3, IAM).
3. Rotação da senha do `neondb_owner` (**não feita**: não estava entre as ações autorizadas; se feita, atualizar `/devfinder-laravel/prod/db-url` no SSM).
