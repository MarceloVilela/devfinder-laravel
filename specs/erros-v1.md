# Inventário de erros do v1 (`php-codei`, commit `3229c4c`) e mapa para exceções tipadas

> Insumo da ADR 0008. Fonte: leitura de `../php-codei/app/Controllers`, `Filters` e `Libraries`
> em 2026-10-02. **Não foi executado contra o v1**: a confirmação do corpo exato de cada resposta
> é tarefa da Fase 0 (rodar os `.http` contra o v1 local, ver `oraculo-v1.md`).

| Origem no v1 | Status | Corpo | Exceção tipada proposta (`php-laravel`) |
|---|---|---|---|
| `RequiredAuthFilter` — sem token | 401 | `{"error":"Token not provided."}` | `TokenNotProvided` |
| `RequiredAuthFilter` — token inválido/expirado ou Dev inexistente | 401 | `{"error":"Token invalid."}` | `TokenInvalid` |
| `DevReactionController` — alvo inexistente | 400 | `{"error":"Dev not exists"}` | `DevNotFound` (renderiza 400, por contrato) |
| `ChannelReactionController` — alvo inexistente | 400 | `{"error":"Channel not exists"}` | `ChannelNotFound` (renderiza 400, por contrato) |
| `VideoController::store` — canal não encontrado | 400 | `{"errorMessage":"channel(<nome>) not found, for: <título>","title","url","channel","channel_url","thumbnail"}` | `ChannelNotFoundForVideo` |
| `VideoController::store` — vídeo já existe | 409 | `{"errorMessage":"video(<título>) already exists", ...campos do vídeo}` | `VideoAlreadyExists` |
| `VideoIngestor` — canal não encontrado no lote | (item de `errors` no resumo) | `{"errorMessage":"channel(<nome>) not found, for: <título>", ...}` | `ChannelNotFoundForVideo` (coletada, não lançada) |
| `AuthController::callback` — falha na troca do `code` | 500 (não tratada) | HTML ou JSON do framework | `GithubExchangeFailed` → 502, causa no log |
| `GET /video/{id}` — vídeo inexistente | **200** | `null` | **preservar** (paridade com `Video.findOne` do Mongo) |
| Corpo ausente ou campo faltando em `POST` | **sem validação** (indexa `$body['title']` direto) | aviso/500 do framework | **divergência**: Form Request responde 422 (registrar no registro de divergências) |
| `POST /devs` — username inexistente no GitHub | 500 (`RuntimeException` do `DevModel::findOrCreate`) | HTML/JSON do framework | **divergência D-6**: `GithubUserNotFound` → 404; `GithubUnavailable` → 502 |
| Rota inexistente | 404 | página do framework | JSON `{"error":"Not found."}` (a confirmar no contrato) |

## Pontos a decidir (viram ADR ou divergência aprovada)

1. **422 para validação**: o original e o v1 não validam; o contrato não define o formato. **Aprovado (D-3)**: 422 com
   `{"error":"...","errors":{campo:[...]}}`.
2. **Erro de infraestrutura** (banco fora, timeout externo): 503 com `{"error":"Service unavailable."}` e causa
   só no log (lição do `transcript`: `Query failed` sem causa custou horas).
3. **Dois formatos** (`error` e `errorMessage`) convivem por herança do contrato: não unificar.
