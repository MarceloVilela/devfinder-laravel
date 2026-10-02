# Ferramentas de contexto — avaliação (Fase 0, tempo limitado)

> Não bloqueia o PR. Regra do plano: só entra ferramenta que responda uma pergunta concreta, com teste real.

## Context7

- **Cobertura confirmada pela API pública** (`context7.com/api/v1/search`, 2026-10-02), **não** pelo MCP:

  | Consulta | Resultado relevante |
  |---|---|
  | `laravel` | `/laravel/docs` (5505 snippets, trust 9.5), `/laravel/laravel`, docs do 12.x e do 13.x |
  | `bref` | `/brefphp/bref` (969 snippets, trust 9) |
  | `laravel pest` | `/pestphp/pest`, `/pestphp/docs`, `/pestphp/pest-plugin-laravel` |
  | `laravel pint` | `/laravel/pint` (309 snippets, trust 9.5) |

- **MCP não testado**: o `php-laravel` não tem `.mcp.json` e esta sessão não expõe `resolve-library-id`. Para fechar o
  item: `claude mcp add --scope project context7 -- npx -y @upstash/context7-mcp`, aprovar em uma sessão aberta aqui e
  chamar `resolve-library-id("laravel/framework")` e `("bref")`. Limite do plano gratuito conhecido do v1: 1.000
  chamadas por mês e 60 por hora sem chave (a conferir de novo).
- **Pergunta concreta que ele responde**: comportamento de API por versão (Laravel e Bref) na hora de escrever
  código, e não comportamento em runtime. Os achados caros do v1 (CI, env, N+1) vieram de rodar e medir.

## Skills de Laravel (busca, **nada foi instalado**)

`npx skills find laravel` listou, entre outras, `jeffallan/claude-skills@laravel-specialist` (22,4 mil instalações),
`affaan-m/ecc@laravel-security`, `@laravel-patterns`, `@laravel-tdd`, `@laravel-verification` e
`asyrafhussin/agent-skills@laravel-best-practices`. Nenhuma foi lida nem avaliada quanto a risco: o v1 só fez essa
avaliação para a skill de CodeIgniter. **Recomendação**: não instalar agora; instalar uma só quando houver uma pergunta
concreta e depois de ler o conteúdo dela.

## DeepWiki

Não avaliado nesta rodada.
