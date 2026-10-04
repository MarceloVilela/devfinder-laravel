# Teardown do `php-laravel` (rascunho da Fase 2b, executável por quem não escreveu)

> ADR 0010: o Free Plan da AWS **encerra a conta** ao expirar (6 meses). A decisão de fim de vida (upgrade ou teardown) é tomada até o
> **dia 150**. Data de início do Free Plan: **confirmar em Billing e preencher aqui** (não está registrada em nenhum arquivo).
> Todos os comandos abaixo foram escritos a partir do que o projeto cria; **só o `osls remove` foi executado de verdade (no spike)**.
> Antes de qualquer remoção, rode `scripts/custo.sh` e guarde a saída.

## O que o projeto cria (e o que remover)

| Onde | Recurso | Como nasce | Como remove |
|---|---|---|---|
| AWS `us-east-2` | stack `devfinder-laravel-prod`: Lambda `devfinder-laravel-prod-web`, Function URL, role de execução, log group (7 dias), bucket de artefatos do osls | `osls deploy` | `npx osls@4.4.0 remove --stage prod` |
| AWS SSM | `/devfinder-laravel/prod/{app-key,db-url,jwt-secret,github-client-id,github-client-secret}` (SecureString) | manual (`aws ssm put-parameter`) | passo 3 |
| AWS IAM | provedor OIDC do GitHub e role `devfinder-laravel-github-deploy` | `scripts/aws-bootstrap.sh --apply` | passo 4 |
| AWS Budgets | `devfinder-laravel-consumo-bruto` (o `Orcamento USD 0,01` é anterior ao projeto: **não remover**) | `scripts/aws-bootstrap.sh --apply` | passo 5 |
| Neon | projeto `devfinder-laravel` (`aws-us-east-2`) | criado à mão | passo 6 |
| GitHub | variável `AWS_DEPLOY_ROLE_ARN`, secret `DIRECT_DATABASE_URL` | à mão | passo 7 |

## Passos (nesta ordem)

1. **Congelar**: avisar que a API vai sair do ar; guardar `scripts/custo.sh > custo-final.txt`. Se for virar upgrade em vez de teardown, **pare aqui**.
2. **Derrubar a stack**, de um checkout limpo da `main` (precisa de credenciais com o escopo da role de deploy):
   ```sh
   composer install --no-dev && npx osls@4.4.0 remove --stage prod
   ```
   Confirmar que voltou vazio: `aws cloudformation list-stacks --region us-east-2 --stack-status-filter CREATE_COMPLETE UPDATE_COMPLETE`,
   `aws lambda list-functions --region us-east-2` e `aws s3 ls | grep devfinder-laravel`. Se um log group ficou
   (`aws logs describe-log-groups --log-group-name-prefix /aws/lambda/devfinder-laravel`), remover com `aws logs delete-log-group`.
3. **Parâmetros do SSM**:
   `aws ssm delete-parameters --region us-east-2 --names /devfinder-laravel/prod/app-key /devfinder-laravel/prod/db-url /devfinder-laravel/prod/jwt-secret /devfinder-laravel/prod/github-client-id /devfinder-laravel/prod/github-client-secret`
4. **IAM**: `aws iam delete-role-policy --role-name devfinder-laravel-github-deploy --policy-name deploy`, depois
   `aws iam delete-role --role-name devfinder-laravel-github-deploy` e, se **nenhum outro projeto** usa o GitHub como emissor,
   `aws iam delete-open-id-connect-provider --open-id-connect-provider-arn arn:aws:iam::<ACCOUNT_ID>:oidc-provider/token.actions.githubusercontent.com`.
5. **Orçamento**: `aws budgets delete-budget --account-id <ACCOUNT_ID> --budget-name devfinder-laravel-consumo-bruto`.
6. **Neon**: `npx neonctl projects delete falling-meadow-15025289` (apaga o banco inteiro: **exporte antes** com `pg_dump` pela URL direta se quiser os dados).
   Antes de apagar, rotacionar a senha do `neondb_owner` já não importa; depois de apagar, a senha deixa de existir.
7. **GitHub**: remover a variável e o secret; arquivar o repositório ou remover o `deploy.yml` (sem ele, um push na `main` falharia por falta de role).
8. **Prova final**: `scripts/custo.sh` sem stack, sem função, sem log group, sem bucket; Cost Explorer no dia seguinte (atrasa horas).

## O que NÃO fazer
- Não apagar a role de deploy antes da stack: sem ela o `osls remove` do CI não roda (use credenciais próprias).
- Não confiar no "US$ 0,00" do mesmo dia: o Cost Explorer atrasa. A prova é o inventário de recursos (passo 8).
- Não deixar o repositório com o `deploy.yml` ativo e sem a role: cada push na `main` falha e polui o histórico.
