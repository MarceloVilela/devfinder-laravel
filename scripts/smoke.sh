#!/bin/sh
# Smoke test: FALHA (exit 1) se qualquer asserção quebrar. Usa só curl e sh.
# Uso: BASE_URL=http://localhost:8082 ORIGIN=http://localhost:3000 scripts/smoke.sh
# Com FUNCTION_NAME (+ credenciais AWS), confere também a configuração viva da função (APP_DEBUG, segredos).
set -u
BASE_URL="${BASE_URL:?defina BASE_URL (sem barra final)}"
ORIGIN="${ORIGIN:?defina ORIGIN (a origem CORS configurada)}"
FAIL=0
ok()   { printf 'ok   %s\n' "$1"; }
fail() { printf 'FAIL %s\n' "$1"; FAIL=1; }
want() { [ "$2" = "$3" ] && ok "$1" || fail "$1 (esperado '$3', veio '$2')"; }
has()  { printf '%s' "$2" | grep -q -i -- "$3" && ok "$1" || fail "$1 (não achei '$3')"; }
hasnt(){ printf '%s' "$2" | grep -q -i -- "$3" && fail "$1 (achei '$3')" || ok "$1"; }

# --- raiz e cabeçalho de id de requisição
R=$(curl -sS -i --max-time 30 "$BASE_URL/health"); CODE=$(printf '%s' "$R" | head -1 | cut -d' ' -f2)
want "GET /health responde 200" "$CODE" "200"
has  "resposta tem X-Request-Id" "$R" '^x-request-id: [A-Za-z0-9._-]\{8,\}'

B=$(curl -sS --max-time 30 "$BASE_URL/v1")
want "GET /v1 devolve o AppInfo do contrato" "$B" '{"appname":"DevFinder"}'

# --- o id enviado pelo cliente volta; id perigoso é descartado
R=$(curl -sS -i --max-time 30 -H 'X-Request-Id: smoke-abc12345' "$BASE_URL/health")
has "X-Request-Id seguro é reaproveitado" "$R" '^x-request-id: smoke-abc12345'

# --- CORS (D-4): origem explícita, nunca *, sem credenciais
R=$(curl -sS -i --max-time 30 -H "Origin: $ORIGIN" "$BASE_URL/v1")
has   "CORS libera a origem configurada" "$R" "^access-control-allow-origin: $ORIGIN"
hasnt "CORS não usa curinga" "$R" '^access-control-allow-origin: \*'
hasnt "CORS não envia Allow-Credentials" "$R" '^access-control-allow-credentials'
R=$(curl -sS -i --max-time 30 -H 'Origin: https://evil.example.test' "$BASE_URL/v1")
hasnt "CORS não ecoa origem desconhecida" "$R" 'access-control-allow-origin: https://evil'

# --- erro sem vazar detalhe interno
R=$(curl -sS -i --max-time 30 "$BASE_URL/v1/rota-que-nao-existe-smoke"); CODE=$(printf '%s' "$R" | head -1 | cut -d' ' -f2)
want "rota inexistente dá 404" "$CODE" "404"
# só o CORPO: os cabeçalhos da Function URL trazem `x-amzn-trace-id`, que não é vazamento
BODY=$(curl -sS --max-time 30 "$BASE_URL/v1/rota-que-nao-existe-smoke")
want  "404 no formato do contrato" "$BODY" '{"error":"Not found."}'
for needle in 'stack' 'trace' '/var/task' 'vendor/' 'Illuminate' '\.php'; do hasnt "erro não contém '$needle'" "$BODY" "$needle"; done

# --- autenticação (Fase 4): sem token 401; login redireciona ao GitHub com state e cookie HttpOnly; token só por cabeçalho
BODY=$(curl -sS --max-time 30 "$BASE_URL/v1/me")
want "GET /v1/me sem token dá 401 no formato do contrato" "$BODY" '{"error":"Token not provided."}'
BODY=$(curl -sS --max-time 30 -H 'Authorization: Bearer garbage.invalid.token' "$BASE_URL/v1/me")
want "GET /v1/me com token inválido dá 401" "$BODY" '{"error":"Token invalid."}'
BODY=$(curl -sS --max-time 30 "$BASE_URL/v1/me?token=garbage.invalid.token")
want "token na query string nunca vale" "$BODY" '{"error":"Token not provided."}'
for route in "POST /v1/devs" "POST /v1/channels" "POST /v1/video" "POST /v1/likes/devs/x" "DELETE /v1/dislikes/channels/x" "GET /v1/likes/devs" "GET /v1/feed/subscriptions"; do
  BODY=$(curl -sS --max-time 30 -X "${route%% *}" -H 'Content-Type: application/json' -d '{}' "$BASE_URL${route#* }")
  want "${route} sem token dá 401 (Fase 5)" "$BODY" '{"error":"Token not provided."}'
done
R=$(curl -sS -i --max-time 30 "$BASE_URL/v1/auth/github"); CODE=$(printf '%s' "$R" | head -1 | cut -d' ' -f2)
want "GET /v1/auth/github redireciona (302)" "$CODE" "302"
has  "redirect vai ao GitHub com state" "$R" '^location: https://github.com/login/oauth/authorize?.*state=[0-9a-f]\{64\}'
hasnt "redirect não pede scope" "$R" '^location:.*scope='
has  "cookie do state é HttpOnly e SameSite=Lax" "$R" '^set-cookie: devfinder_oauth_state=.*httponly.*samesite=lax'
R=$(curl -sS -i --max-time 30 -X POST "$BASE_URL/v1/auth/logout"); CODE=$(printf '%s' "$R" | head -1 | cut -d' ' -f2)
want "POST /v1/auth/logout responde 204" "$CODE" "204"
has  "logout expira o cookie de sessão httpOnly" "$R" '^set-cookie: devfinder_token=.*httponly'
R=$(curl -sS -i --max-time 30 "$BASE_URL/v1/auth/github/callback?code=x&state=0000"); CODE=$(printf '%s' "$R" | head -1 | cut -d' ' -f2)
want "callback com state inválido volta ao front (302)" "$CODE" "302"
hasnt "callback com state inválido não entrega token" "$R" '^location:.*token='
hasnt "callback com state inválido não abre sessão" "$R" '^set-cookie: devfinder_token=[^;]'

# --- /docs serve o contrato
CODE=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 30 "$BASE_URL/docs/openapi.yaml")
want "GET /docs/openapi.yaml responde 200" "$CODE" "200"

# --- banco (opcional: só se o ambiente tem banco; no deploy é obrigatório)
if [ "${EXPECT_DB:-1}" = "1" ]; then
  B=$(curl -sS --max-time 60 "$BASE_URL/health/db")
  want "GET /health/db responde ok" "$B" '{"status":"ok","database":"up"}'
fi

# --- configuração viva da função (só no deploy)
if [ -n "${FUNCTION_NAME:-}" ]; then
  ENVJSON=$(aws lambda get-function-configuration --region "${AWS_REGION:-us-east-2}" --function-name "$FUNCTION_NAME" --query 'Environment.Variables' --output json)
  want "função: APP_DEBUG=false" "$(printf '%s' "$ENVJSON" | python3 -c 'import sys,json;print(json.load(sys.stdin).get("APP_DEBUG"))')" "false"
  want "função: APP_ENV=production" "$(printf '%s' "$ENVJSON" | python3 -c 'import sys,json;print(json.load(sys.stdin).get("APP_ENV"))')" "production"
  PLAIN=$(printf '%s' "$ENVJSON" | python3 -c 'import sys,json;d=json.load(sys.stdin);print(",".join(k for k in ("APP_KEY","DB_URL","JWT_SECRET","GITHUB_CLIENT_SECRET") if not str(d.get(k,"")).startswith("bref-ssm:")))')
  want "função: segredos só como bref-ssm: (sem texto)" "$PLAIN" ""
fi

[ "$FAIL" -eq 0 ] && { echo "SMOKE OK"; exit 0; }
echo "SMOKE FALHOU"; exit 1
