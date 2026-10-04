# Arquitetura alvo do código (ADRs 0005, 0006, 0008)

> Esboço no papel, sem código. Os nomes são propostas; a escolha de DTO e de teste de arquitetura
> fica **pendente de teste com exemplo real** (ver "Pendências").

## Estrutura

```
app/
├── Features/<Feature>/{Actions,Data,Http/{Controllers,Requests,Resources},Models,Policies,Queries,Exceptions}
└── Shared/{Exceptions,Http,Support}
routes/api.php   rota → Controller, prefixo /v1
```

Regras (verificadas por teste de arquitetura, gate G4; uma feature usa `Actions` e `Data` de outra, nunca `Queries`, `Models`, `Http`, `Support`, `Console` nem `Exceptions`): `Http → Actions → Models/Queries`; controller não toca
Eloquent (exceto `Queries/` em leitura sem regra), cliente HTTP nem `new` de classe de domínio; `Shared` não
importa feature; uma feature usa outra só por Actions e Data; integração externa atrás de interface;
facades só nas bordas; `env()` só em `config/`.

## Mapa operação → fase (as 30 do OpenAPI)

| Fase | Operações |
|---|---|
| 2b | `GET /health`, `GET /health/db` (extra, não faz parte das 30) |
| 3 (11) | `GET /`, `GET /devs`, `GET /devs/{username}`, `GET /channels`, `GET /channels/{searchQuery}`, `GET /description/feed`, `GET /description/category`, `GET /feed/trending`, `GET /feed/channel`, `GET /video/{idYoutubeWatch}`, `GET /search` |
| 4 (3) | `GET /auth/github`, `GET /auth/github/callback`, `GET /me` |
| 5 (14) | `POST /devs`, `POST /channels`, `POST /video`; `POST`/`DELETE` de `/likes/devs/{u}`, `/dislikes/devs/{u}`, `/likes/channels/{u}`, `/dislikes/channels/{u}`; `GET /likes/devs`, `GET /dislikes/devs`, `GET /feed/subscriptions` |
| 6 (2) | `POST /video/refresh`; `POST /channels/refresh` (**fora do escopo**, decidido em 2026-10-02, ver `auditoria-original.md` A1) |

## Caso de leitura no papel — `GET /devs/{username}` (sem regra)

```
routes/api.php            GET /devs/{username}  →  ShowDevController
ShowDevController         recebe {username}; chama DevQueries::byUsername(); devolve DevResource
DevQueries (Queries/)     Dev::query()->where('username', $u)->withCount(...)->firstOrFail()
DevResource (Resources/)  shape exato do schema `Dev` do OpenAPI; sem colunas internas
erro                      DevNotFound → handler global → formato do contrato
```

Sem Action, porque não há regra. Orçamento de queries: ≤ 3, independente de dados (definido na Fase 1).

## Caso de escrita no papel — `POST /video` (com regra)

```
routes/api.php            POST /video (middleware auth)  →  StoreVideoController
StoreVideoRequest         valida title, url, channel, channel_url, thumbnail?  (422 se inválido)
StoreVideoData (Data/)    DTO readonly criado de $request->validated()
StoreVideo (Actions/)     recebe dependências pelo construtor; regra:
                            1. normalizar url (remove &pp=) e thumbnail
                            2. canal não encontrado → ChannelNotFoundForVideo (400)
                            3. url já existe       → VideoAlreadyExists   (409)
                            4. thumbnail vazio     → padrão do YouTube
                            5. grava em transação e devolve o Video
VideoResource             shape do schema `Video`; status 201
```

Teste de unidade da Action (sem HTTP); teste de feature com PostgreSQL real; teste de contrato do shape de
201, 400 e 409; teste de negação (sem token → 401).

## Pendências (decididas na Fase 0 com exemplo real, não por descrição)

- [ ] DTO: classes `readonly` próprias (proposta) ou `spatie/laravel-data`.
- [ ] Teste de arquitetura: Pest `arch()` ou PHPat. Requer o esqueleto Laravel, então o teste real acontece no spike inicial.
- [ ] Ferramenta de contrato (Spectator, laravel-openapi-validator ou Gesso): idem.
