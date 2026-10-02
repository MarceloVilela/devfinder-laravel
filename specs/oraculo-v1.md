# Oráculo v1 (`php-codei`) — como subir e o que ele cobre

- **Referência**: `../php-codei`, commit `3229c4c` (`HEAD` de `main` conferido em 2026-10-02). Nada é editado lá.
- **Deploy do Render**: respondeu **200** em `GET /v1/devs?page=1` em 2026-10-02. Serve para leitura e para
  conferir o shape. **Não** rodar `.http` de escrita contra ele (grava no banco de produção, ver A12).
- **Oráculo para escrita e para o G3**: v1 **local**, via Docker Compose do próprio v1
  (`docker compose up` em `../php-codei`, porta `8081`, MySQL 8.4), com o mesmo dataset de paridade.
- **Ambientes dos `.http`**: `v1` (`localhost:8081`), `local` (`php-laravel`, `localhost:8082`, provisória) e
  `real` (a definir na Fase 2b).
- **Cobertura**: 27 das 30 operações. `GET /search`, `GET /feed/subscriptions` e `POST /channels/refresh` **não
  existem no v1** (404). Para elas o oráculo é o `devfinder-api` original e o contrato (ver `auditoria-original.md`, A1).
- **Ferramentas locais**: `docker` 29.8 disponível; **não há `php` nem `composer` no host**, então todo comando
  PHP roda em container (consequência para a Fase 2a: o `docker compose` local inclui a aplicação, não só o Postgres).

## Containers do v1 local (renomeados em 2026-10-02)

Os containers do v1 (CodeIgniter 4 + MySQL 8.4, porta `8081`) foram renomeados com `docker rename`, a pedido do usuário:

| Nome original (compose do v1) | Nome atual | Papel |
|---|---|---|
| `v1-nginx-1` | `nginx-laravel` | Nginx, `localhost:8081` |
| `devfinder-codeigniter` | `definder-laravel` (grafia do usuário; pode ser `devfinder-laravel`) | PHP-FPM 8.3 do v1 |
| `v1-mysql-1` | `mysql-laravel` | MySQL 8.4, `localhost:3306` |

- **Atenção**: apesar do nome, **são o v1, não o `php-laravel`**. Quando o `php-laravel` tiver os seus containers
  (Fase 2a), os nomes colidem: renomear um dos lados antes.
- O rename foi feito só no Docker. O `docker-compose.yml` do v1 (cópia temporária do commit `3229c4c`) mantém os
  nomes originais, então um novo `docker compose up` ali tenta recriar `devfinder-codeigniter`. Para parar tudo:
  `docker stop nginx-laravel definder-laravel mysql-laravel` (ou `docker compose down -v` na cópia temporária, que
  também remove o volume do MySQL).
- Os serviços continuam se achando pelos aliases do compose (`app`, `mysql`); a resposta de `localhost:8081/v1/` e de
  `/v1/devs` foi conferida depois do rename (200).

## Pendente (executar quando o usuário autorizar subir o v1)

- [x] **Feito em 2026-10-02**: saída resumida em `acceptance/execucao-v1-baseline.log` (62 requisições). O `.env` do
      `php-codei` aponta `database.defaultGroup = tidbcloud` (**produção**), então o v1 foi subido a partir de uma
      cópia do commit `3229c4c` com o `.env.example` (só MySQL local); o diretório `../php-codei` não foi tocado.
      Comando original previsto: `docker compose up` no `php-codei` e rodar `npx httpyac send *.http --env v1 --all` a partir de
      `specs/acceptance/` (**excluindo `search.http`**, que o v1 não implementa), guardando a saída em `specs/acceptance/execucao-v1-baseline.log`.
- Esperado no baseline: as requisições de `GET /feed/subscriptions` (em `videos.http`) dão 404 no v1, pois a rota não existe lá.
- [ ] Normalização de ids e datas para o diff (script em `specs/acceptance/normalize.*`, Fase 0 ou 2a).
