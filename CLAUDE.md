# CLAUDE.md

Orienta o Claude Code neste diretório. **Máximo de 100 linhas** (regra do `../../CLAUDE.md`).

## O que é

`php-laravel`: a API do DevFinder em **Laravel + PostgreSQL (Neon) em AWS Lambda via Bref**, com o mesmo
contrato público de `../devfinder-api` e custo zero. Reescrita do `../php-codei` (v1, CodeIgniter 4/MySQL),
que fica **intocado** e serve de oráculo de paridade. Não alterar `../devfinder-api`, `../devfinder-next`,
`../serverless`, `../reactjs` nem `../php-codei`.

## Leia nesta ordem

1. [`plan.md`](./plan.md): plano spec-driven, fases, gates G0–G4 e a Definição de pronto.
2. [`specs/README.md`](./specs/README.md): estado dos artefatos.
3. [`specs/fase-0-especificacao.md`](./specs/fase-0-especificacao.md): achados e pendências da Fase 0.

## Regras do projeto

- **Spec antes do código**, nesta ordem: spec → teste → implementação. Nenhuma fase avança sem a spec escrita.
- **Commit, push e PR só quando o usuário pedir explicitamente.** Implementar até o critério de aceite bater é
  trabalho autônomo; abrir PR não é. **Sem co-autor do Claude** (nem `Co-Authored-By` nem rodapé "Generated
  with Claude Code") em commits e PRs. Notas locais ficam em `CLAUDE.local.md` (não versionado).
- **Um PR por fase**, branch `fase-N-nome` a partir de `main`; a fase seguinte só começa com o PR anterior mergeado.
- **Conferir `echo $?` sem pipe** depois de qualquer comando que o CI também roda (PHPUnit saía "OK" com código 1).
- **Reproduzir o CI byte a byte** (container limpo, `.env` gerado pelo workflow) antes de PR que toque CI,
  `composer.*`, `.env*` ou deploy.
- **Aceite se prova rodando os `.http` e guardando a saída** em `specs/execucao-fase-N.log`, não com prosa.
- Variáveis de ambiente só `[A-Z0-9_]` (ponto no nome sumiu em silêncio no Render).
- `env()` só dentro de `config/` (quebra com `config:cache`, que o Lambda usa).
- Referência cruzada nos documentos só para arquivo que existe com aquele nome (conferir antes).
- O host **não tem `php` nem `composer`**: todo comando PHP roda em container Docker.

## Regra de custo (reformulada, ADR 0010)

Custo zero **durante o Free Plan da AWS**, com decisão de fim de vida (upgrade ou teardown) até o dia 150. O
Free Plan encerra a conta ao expirar. Allowlist de recursos no CI; AWS Budgets com alerta em US$ 1; nunca
criar recurso fora da allowlist (RDS, NAT, API Gateway não previsto, Secrets Manager, ElastiCache, EC2, VPC).
Segredo em nota ou arquivo versionado é incidente (chave AWS, token, cartão).

## Dados

O dump real de `../php-codei/specs/seed/` tem nomes de terceiros (marca e LGPD): **só por caminho relativo,
nunca copiado para este repositório**. O repositório usa seed sintético versionado. Os `.http` de escrita
**nunca** rodam contra o deploy do Render do v1 (grava no banco de produção): o oráculo de escrita é o v1 local.

## Arquivos

- `specs/fase-0-openapi.yaml`: contrato único. Divergência se corrige **aqui primeiro**.
- `specs/acceptance/*.http`: casos de aceite, ambientes `v1`, `local` e `real`. Nunca versionar `*.private.*`.
- `specs/adr/`: decisões (todas `proposta` até a evidência existir). `specs/spikes/`: resultado medido.
- `specs/auditoria-original.md`: achados e **registro de divergências aprovadas** (o G3 compara o que não está lá).
- Arquivos com sufixo `__` são notas locais e não vão para o Git.
