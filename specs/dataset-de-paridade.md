# Dataset de paridade (G3) e seed sintético

> Define os dados idênticos que o v1 e o `php-laravel` carregam para o diff do G3, e a fixture usada nos testes.
> **Só dados inventados** (regra do `CLAUDE.local.md`: nada de dados de terceiros no GitHub). O dump real de
> `../php-codei/specs/seed/` fica de fora, inclusive para os `.http`.

## Fonte

Descrição extraída dos seeders de acceptance do v1 (`app/Database/Seeds/Acceptance*.php`, commit `3229c4c`). O
`php-laravel` reproduz o mesmo conteúdo em `database/seeders/` (criado na Fase 2a); este arquivo é a especificação
do conteúdo, independente do framework. Tudo usa o domínio reservado `example.test` e nomes como `Canal Alpha`.

## Conteúdo (estado inicial, antes de qualquer `.http`)

| Entidade | Quantidade | Regra |
|---|---|---|
| Devs | 35 | `dev01` a `dev35`; `name` = `Dev NN`; `avatar` = `https://example.test/avatar/devNN.png`; `bio` = `Bio sintética do dev NN.` (nula nos múltiplos de 3); `created_at` = `2026-01-01 00:00:00` + `NN` minutos |
| Canais | 3 | `Canal Alpha` (`https://youtube.com/alpha`, Tecnologia), `Canal Beta` (`https://youtube.com/beta`, Educação), `Canal Zeta` (`https://youtube.com/zeta`, Testes; sem vídeos nem reações, reservado para escrita). Criados em `2026-01-01` com 1 s de diferença |
| Tags | 3 | `javascript`, `testes`, `react`; Alpha tem `javascript` e `testes`, Beta tem `react` |
| Vídeos | 55 | 20 do Alpha (`vidalpha01`..`vidalpha20`) e 35 do Beta (`vidbeta01`..`vidbeta35`); título `Vídeo Alpha NN` / `Vídeo Beta NN`; `url` = `https://www.youtube.com/watch?v=<id>`; `created_at` = `2026-02-01` + 1 minuto por vídeo, Alpha primeiro; `published_at` nulo |
| Reações de dev | 2 | `dev01` curte `dev02` e descurte `dev03` |
| Reações de canal | 2 | `dev01` segue `Canal Alpha` e ignora `Canal Beta` |

Consequências conferidas no baseline do v1: `GET /devs` página 1 tem 30 itens de 35; `Canal Beta` tem 35 vídeos
(2 páginas, 30 + 5); `dev01` tem `likes=[dev02]`, `deslikes=[dev03]`, `follow=[Alpha]`, `ignore=[Beta]`.

## Regras para o G3

1. **Mesmo estado inicial dos dois lados** e **mesma ordem de execução** dos `.http`. As escritas alteram o
   banco: recriar o banco e a fixture (`migrate:refresh` + seed) antes de cada rodada.
2. **Normalizar antes de comparar**: `_id`/`channel_id`/ids em arrays (o v1 devolve inteiros; o `php-laravel` devolve
   string por D-7: converter o id do v1 para string), `createdAt`/`updatedAt` (o v1 usa `+00:00`), tempos de resposta e a ordem de
   chaves. O script de normalização fica em `specs/tools/` (Fase 2a).
3. **Fora do diff** (sem oráculo v1): `GET /search`, `GET /feed/subscriptions`. Elas são validadas contra o contrato.
4. **Casos que chamam serviço externo** (`POST /devs` com `octocat`, `POST /channels` com `userGithub`): usam o
   GitHub real no v1; no `php-laravel` rodam com `Http::fake` nos testes e com o GitHub real só nos `.http`.
5. **Ingestão**: o `.http` do lote usa só dados da fixture (`Canal Alpha`, `vidalpha01`). O caso original usava um
   canal e um vídeo reais do dump e foi reescrito (2026-10-02). O comando agendado lê uma **fixture congelada e
   sintética**, não o JSONBin real (ver Fase 6 do plano).

## Pendências

- [ ] Escrever o seeder em PHP no `php-laravel` (Fase 2a) e provar que gera os mesmos números (35 / 3 / 55).
- [ ] A fixture de ingestão congelada (arquivo JSON sintético) fica para a Fase 6.
- [ ] O orçamento de queries da Fase 1 precisa de pelo menos 31 itens numa listagem: os 35 devs servem.
