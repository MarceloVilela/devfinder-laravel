#!/bin/sh
# Prepara a conta para o deploy por OIDC: provedor OIDC do GitHub, role de deploy (escopo mínimo) e o 2º orçamento.
# SEM --apply só IMPRIME o que faria (nada é criado). Com --apply CRIA recursos na conta: exige autorização explícita.
# Uso: scripts/aws-bootstrap.sh            # plano
#      scripts/aws-bootstrap.sh --apply    # executa
set -eu
cd "$(dirname "$0")/.."

REGION="${AWS_REGION:-us-east-2}"
ACCOUNT_ID="$(aws sts get-caller-identity --query Account --output text)"
ROLE_NAME="devfinder-laravel-github-deploy"
APPLY=0; [ "${1:-}" = "--apply" ] && APPLY=1

TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
# remove o campo `_comentario` (a IAM rejeita elementos desconhecidos) e troca os marcadores
for f in deploy-role deploy-role-trust; do
  python3 - "$f" "$ACCOUNT_ID" "$REGION" "$TMP" <<'PY'
import json, sys
name, account, region, tmp = sys.argv[1:5]
d = json.load(open(f"specs/iam/{name}.json"))
d.pop("_comentario", None)
s = json.dumps(d, indent=2).replace("<ACCOUNT_ID>", account).replace("<REGION>", region)
open(f"{tmp}/{name}.json", "w").write(s)
PY
done

run() { if [ "$APPLY" -eq 1 ]; then echo "+ $*"; "$@"; else echo "[plano] $*"; fi; }

echo "conta=$ACCOUNT_ID região=$REGION role=$ROLE_NAME apply=$APPLY"

if aws iam list-open-id-connect-providers --query 'OpenIDConnectProviderList[].Arn' --output text | grep -q token.actions.githubusercontent.com; then
  echo "provedor OIDC do GitHub: já existe"
else
  # `--thumbprint-list` é opcional para este emissor (a AWS valida a cadeia de certificados sozinha).
  run aws iam create-open-id-connect-provider --url https://token.actions.githubusercontent.com --client-id-list sts.amazonaws.com
fi

if aws iam get-role --role-name "$ROLE_NAME" >/dev/null 2>&1; then
  echo "role $ROLE_NAME: já existe (atualizando política)"
  run aws iam update-assume-role-policy --role-name "$ROLE_NAME" --policy-document "file://$TMP/deploy-role-trust.json"
else
  run aws iam create-role --role-name "$ROLE_NAME" --assume-role-policy-document "file://$TMP/deploy-role-trust.json" \
    --description "Deploy do devfinder-laravel pelo GitHub Actions (OIDC, branch main)" --max-session-duration 3600
fi
run aws iam put-role-policy --role-name "$ROLE_NAME" --policy-name deploy --policy-document "file://$TMP/deploy-role.json"

# 2º orçamento: o existente inclui créditos (só alerta quando o crédito acaba); este NÃO inclui, e alerta no consumo bruto.
if aws budgets describe-budget --account-id "$ACCOUNT_ID" --budget-name "devfinder-laravel-consumo-bruto" >/dev/null 2>&1; then
  echo "orçamento devfinder-laravel-consumo-bruto: já existe"
else
  EMAIL="${BUDGET_EMAIL:?defina BUDGET_EMAIL para o alerta}"
  cat > "$TMP/budget.json" <<JSON
{"BudgetName":"devfinder-laravel-consumo-bruto","BudgetLimit":{"Amount":"1.0","Unit":"USD"},"TimeUnit":"MONTHLY","BudgetType":"COST",
 "CostTypes":{"IncludeCredit":false,"IncludeRefund":false,"IncludeDiscount":false,"IncludeTax":true,"IncludeSubscription":true,"UseBlended":false}}
JSON
  cat > "$TMP/notif.json" <<JSON
[{"Notification":{"NotificationType":"ACTUAL","ComparisonOperator":"GREATER_THAN","Threshold":100,"ThresholdType":"PERCENTAGE"},
  "Subscribers":[{"SubscriptionType":"EMAIL","Address":"$EMAIL"}]}]
JSON
  run aws budgets create-budget --account-id "$ACCOUNT_ID" --budget "file://$TMP/budget.json" --notifications-with-subscribers "file://$TMP/notif.json"
fi

echo
echo "Depois: no GitHub, Settings > Secrets and variables > Actions:"
echo "  variável AWS_DEPLOY_ROLE_ARN = arn:aws:iam::$ACCOUNT_ID:role/$ROLE_NAME   (não é segredo)"
echo "  secret   DIRECT_DATABASE_URL = URL direta do Neon (só para o migrate)"
