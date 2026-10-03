#!/bin/sh
# Inventário de custo e de recursos, SOMENTE LEITURA (nenhum comando altera a conta). Uso: scripts/custo.sh
# O Cost Explorer atrasa horas: "US$ 0" de hoje não é prova. Compare com o orçamento e com o inventário abaixo.
set -u
REGION="${AWS_REGION:-us-east-2}"
ACCOUNT_ID="$(aws sts get-caller-identity --query Account --output text)"
STACK="devfinder-laravel-prod"
FN="$STACK-web"
START="${START:-$(date -u -d '-7 days' +%F)}"; END="$(date -u -d '+1 day' +%F)"

echo "== conta $ACCOUNT_ID, região $REGION, de $START a $END"
echo; echo "== custo por serviço (UnblendedCost, bruto, por mês; mês aparece em linhas separadas)"
aws ce get-cost-and-usage --time-period "Start=$START,End=$END" --granularity MONTHLY --metrics UnblendedCost \
  --group-by Type=DIMENSION,Key=SERVICE --query 'ResultsByTime[].Groups[].[Keys[0],Metrics.UnblendedCost.Amount]' --output text 2>&1 | sort -k2 -nr | head -15

echo; echo "== orçamentos"
aws budgets describe-budgets --account-id "$ACCOUNT_ID" \
  --query 'Budgets[].[BudgetName,BudgetLimit.Amount,CalculatedSpend.ActualSpend.Amount]' --output text 2>&1

echo; echo "== stacks vivas em $REGION"
aws cloudformation list-stacks --region "$REGION" --stack-status-filter CREATE_COMPLETE UPDATE_COMPLETE UPDATE_ROLLBACK_COMPLETE ROLLBACK_COMPLETE \
  --query 'StackSummaries[].[StackName,StackStatus]' --output text 2>&1
echo; echo "== recursos da stack $STACK (tudo precisa estar na allowlist: scripts/allowlist.cjs)"
aws cloudformation describe-stack-resources --region "$REGION" --stack-name "$STACK" \
  --query 'StackResources[].[ResourceType,LogicalResourceId]' --output text 2>&1

echo; echo "== funções Lambda, memória e arquitetura"
aws lambda list-functions --region "$REGION" --query 'Functions[].[FunctionName,Runtime,MemorySize,Architectures[0]]' --output text 2>&1
echo; echo "== limite de concorrência da conta"
aws lambda get-account-settings --region "$REGION" --query '{limite:AccountLimit.ConcurrentExecutions,semReserva:AccountLimit.UnreservedConcurrentExecutions}' --output json 2>&1

echo; echo "== log groups e retenção (None = nunca expira = vazamento de custo)"
aws logs describe-log-groups --region "$REGION" --query 'logGroups[].[logGroupName,retentionInDays,storedBytes]' --output text 2>&1

echo; echo "== invocações e concorrência máxima da função nas últimas 24 h"
for M in Invocations ConcurrentExecutions; do
  STAT=Sum; [ "$M" = ConcurrentExecutions ] && STAT=Maximum
  printf '%s: ' "$M"
  aws cloudwatch get-metric-statistics --region "$REGION" --namespace AWS/Lambda --metric-name "$M" \
    --dimensions "Name=FunctionName,Value=$FN" --start-time "$(date -u -d '-1 day' +%FT%TZ)" --end-time "$(date -u +%FT%TZ)" \
    --period 86400 --statistics "$STAT" --query "Datapoints[0].$STAT" --output text 2>&1
done

echo; echo "== buckets S3 do projeto"
aws s3api list-buckets --query 'Buckets[?starts_with(Name, `devfinder-laravel`)].Name' --output text 2>&1
echo; echo "== recursos que NUNCA devem existir (se algo aparecer abaixo, é incidente de custo)"
aws rds describe-db-instances --region "$REGION" --query 'DBInstances[].DBInstanceIdentifier' --output text 2>&1 | sed 's/^/rds: /'
aws ec2 describe-nat-gateways --region "$REGION" --filter Name=state,Values=available,pending --query 'NatGateways[].NatGatewayId' --output text 2>&1 | sed 's/^/nat: /'
aws ec2 describe-instances --region "$REGION" --filter Name=instance-state-name,Values=running,pending --query 'Reservations[].Instances[].InstanceId' --output text 2>&1 | sed 's/^/ec2: /'
aws secretsmanager list-secrets --region "$REGION" --query 'SecretList[].Name' --output text 2>&1 | sed 's/^/secretsmanager: /'
aws apigatewayv2 get-apis --region "$REGION" --query 'Items[].Name' --output text 2>&1 | sed 's/^/apigateway: /'
aws elasticache describe-cache-clusters --region "$REGION" --query 'CacheClusters[].CacheClusterId' --output text 2>&1 | sed 's/^/elasticache: /'
