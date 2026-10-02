# Acceptance — como disparar as requests `.http`

> **Nota do `php-laravel` (2026-10-02)**: copiado do v1 sem reescrever o corpo. Onde o texto citar CodeIgniter,
> MySQL, `spark` ou Docker Compose do v1, leia como histórico do v1. Ambientes aqui: `v1` (Docker do `php-codei`,
> porta 8081), `local` (`php-laravel`, porta 8082, provisória) e `real` (a definir na Fase 2b). Os `.http` de
> escrita **nunca** rodam contra o Render do v1. Três operações (`search`, `feed-subscriptions`, `channels/refresh`)
> não existem no v1: o resultado esperado nelas vem do contrato e do original (`../auditoria-original.md`, A1).
> Reescrita completa deste README fica para a Fase 2a.
>
> **Arquivos por feature** (condensados em 2026-10-02 a partir dos 19 do v1; as 62 requisições foram
> preservadas): `app`, `auth`, `devs`, `channels`, `videos` (inclui `feed-*`), `reactions`, `ingestion`,
> `description` e `search`. Os nomes antigos citados mais abaixo neste README mapeiam assim:
> `devs-*`/`dev-show` → `devs.http`; `channel*` → `channels.http`; `video-show`/`video-store`/`feed-*` →
> `videos.http`; `likes-dislikes-*` → `reactions.http`; `video-refresh*` → `ingestion.http`; `me`/`auth-github` →
> `auth.http`. `app.http` e `search.http` são novos (o v1 não os tinha). `POST /channels/refresh` não tem caso
> (fora do escopo). Cuidado: `auth.http` ainda cita chaves de `.env` com ponto (`auth.githubClientId`), do
> CodeIgniter; no `php-laravel` os nomes são `[A-Z0-9_]` (corrigir na spec da Fase 4).


Cada `.http` nesta pasta é um caso de aceite de uma fase (`../fase-*.md` documenta o
critério). Adaptado de `../../serverless/specs/acceptance/` (mesmo domínio, mesmo contrato —
ver `../fase-0-openapi.yaml`), trocando o que é específico da stack AWS/DynamoDB por
Docker/MySQL/CodeIgniter. Os arquivos usam variáveis (`{{baseUrl}}`, `{{authToken}}`,
`{{ghostAuthToken}}`) resolvidas por um **ambiente** — não tem valor fixo dentro do `.http`,
de propósito, pra rodar o mesmo arquivo contra local ou o deploy real só trocando o ambiente
selecionado.

## Os dois arquivos de ambiente (e por que dois)

- **`http-client.env.json`** (versionado) — só o que é seguro publicar: `baseUrl` de cada
  ambiente (`local`, `real`). Convenção do **JetBrains HTTP Client** (WebStorm/IntelliJ,
  suporte nativo a `.http`), adotada aqui mesmo por quem usa outro editor.
- **`http-client.private.env.json`** (gitignored — ver `../../.gitignore`) — os campos
  sensíveis: `authToken`, `ghostAuthToken`. O JetBrains HTTP Client faz merge automático dos
  dois arquivos por nome de ambiente; **não existe ainda** (nem vai existir até a Fase 4, que
  é quando o JWT/`APP_SECRET` passam a existir de verdade). Depois de clonar o repo, ele
  também não vai existir — precisa gerar os tokens de novo (comandos abaixo) antes de rodar
  qualquer `.http` que precise de auth (`me.http`, `*-store.http`, `likes-dislikes-*.http`,
  etc.).

## Disparando as requests

### WebStorm / IntelliJ (HTTP Client nativo)

Abra o `.http`, escolha o ambiente no dropdown que aparece ao lado do ícone de "run" (▶) de
cada request — `local` ou `real` — e clique nele. Os dois arquivos de ambiente acima são lidos
automaticamente, sem configuração extra.

### VS Code (extensão **REST Client**, `humao.rest-client`)

Essa extensão **não lê** `http-client.env.json`/`.private.env.json` — são convenção do
JetBrains. Pra VS Code, as mesmas variáveis vivem em `.vscode/settings.json`
(`rest-client.environmentVariables`, na raiz do projeto — também gitignored, mesmo motivo).
Se esse arquivo não existir ainda: copie a estrutura de `http-client.env.json` +
`http-client.private.env.json` num único objeto, por ambiente, sob essa chave. Depois, com o
`.http` aberto: clique em **"No Environment"** no canto inferior direito da barra de status
(ou `Ctrl+Alt+E`/`Cmd+Alt+E`) → escolha `local` ou `real` → "Send Request" acima de cada bloco
`###`.

### Linha de comando (`curl`), sem depender de nenhuma extensão

Todo `.http` aqui é reproduzível manualmente:
```bash
curl -s -w '\n%{http_code}\n' "<baseUrl>/me" -H "Authorization: Bearer <authToken>"
```
trocando `<baseUrl>` e `<authToken>` pelos valores do ambiente que você quer testar.

## Gerando os tokens do `http-client.private.env.json` (a partir da Fase 4)

`authToken` = JWT válido de um Dev que existe na base; `ghostAuthToken` = JWT de um username
que **não** existe (pra exercitar o caminho de erro `DevProfile not exists`). Payload
`{ username }`, validade `7d` (mesma decisão da versão `../../serverless`, ver `../plan.md`
Fase 4). Sem equivalente a Secrets Manager/SSM aqui — o secret é sempre `.env` local
(`APP_SECRET`), inclusive no deploy real (ver `CLAUDE.md`, "Regra de custo" — não há custo
fixo mensal a evitar fora da AWS, então não há motivo para um serviço gerenciado de segredo
nesta stack):

```bash
php -r "
require 'vendor/autoload.php';
use Firebase\JWT\JWT;
\$secret = getenv('APP_SECRET');
echo 'authToken: ' . JWT::encode(['username' => '<um dev que exista no seed>', 'exp' => time() + 7*86400], \$secret, 'HS256') . PHP_EOL;
echo 'ghostAuthToken: ' . JWT::encode(['username' => 'usuario-fantasma-inexistente', 'exp' => time() + 7*86400], \$secret, 'HS256') . PHP_EOL;
"
```

Cole os dois valores em `http-client.private.env.json`, no bloco do ambiente correspondente
(`local` ou `real` — cada um tem seu próprio `APP_SECRET`, então gere os tokens separadamente
por ambiente).

## Pré-requisitos por ambiente

| | `local` | `real` |
|---|---|---|
| API rodando | `docker compose up` (raiz do projeto) | Já deployada na VM real (ver `plan.md` Fase 2) |
| Banco | MySQL do `docker-compose.yml`, populado via `php spark migrate` + seeder | MySQL gerenciado real (provedor a decidir na Fase 2, ver `../infra-pending.md`), populado do mesmo jeito |
| Secret pro token | `.env` local (`APP_SECRET`) | `.env` do host real (mesmo mecanismo, valor diferente) |
| Username usado no token | um dev que exista no seu seed (a definir na Fase 1) | um que exista na base real |
