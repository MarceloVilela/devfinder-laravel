# Spike 2a — ferramentas adiadas da Fase 0 (contrato, DTO, arquitetura, Scramble)

> Medido em 2026-10-03, PHP 8.4.26, Laravel 13, Pest 5, dentro de cópias descartáveis do esqueleto (uma por candidata,
> fora do repositório). Insumo das ADRs 0005 a 0007. A decisão final de cada ADR é do usuário.

## 1. Ferramenta de contrato (ADR 0007)

Mesma bateria para as três, contra `specs/fase-0-openapi.yaml` **sem editar o YAML** (OpenAPI 3.0.3, `servers` com `/v1`):

1. resposta real de `GET /v1/devs/dev01` contra o schema `Dev` **passa**;
2. a mesma resposta contra uma cópia do YAML com `_id: integer` **reprova** (prova de que valida de verdade);
3. rota registrada e **não documentada** (`GET /v1/nodoc`, 200) **reprova**.

| | Spectator 3.0.4 | laravel-openapi-validator 2.0.2 (Kirschbaum) | Gesso 2.6.0 |
|---|---|---|---|
| 1. passa no caso real | sim | sim | sim |
| 2. reprova schema errado | **não** com rota registrada no teste; sim na raiz `/v1` | sim (`Key: _id`) | sim (`[/_id] The data (string) must match the type: integer`) |
| 3. reprova rota fora do contrato | **não** (passou em silêncio, inclusive `/health`) | sim (`no such operation [/v1/nodoc]`) | sim (`No matching path ... closest spec paths: ...`) |
| Raiz `GET /` com prefixo `/v1` | exige `path_prefix` | **falha** (`no such operation [/v1]`), sem contorno limpo | falha se strip dá `''`; **contorno limpo**: path explícito `/` |
| OpenAPI 3.1 | declara | não (base: `league/openapi-psr7-validator`) | 3.0, 3.1 e 3.2 |
| Mensagem de falha | boa | curta | a melhor: caminho do campo, rotas mais próximas e `curl` para reproduzir |
| Paridade de rotas | `spectator:routes` | não | `gesso:routes --fail-on-undocumented --fail-on-unimplemented` |
| Cobertura por operação e status | não | não | sim (extensão do PHPUnit, imprime ✓/✗ por operação) |
| Dependência extra | — | PSR-7 bridge, league | `symfony/yaml` (dev) |

**Resultado: Gesso.** Única que passou nas três provas e que resolve a raiz. Spectator deixou passar um desvio
silencioso (o pior defeito para uma ferramenta de contrato); Kirschbaum não valida o `GET /` do contrato sem editar o
YAML. É **só dependência de desenvolvimento**: não entra no pacote do Lambda (`composer install --no-dev`).
Risco: projeto jovem (≈6 mil downloads, contra ≈2 milhões do Spectator); se parar, o contrato continua sendo o YAML e
a troca é de um trait. Efeito colateral útil: `gesso:routes` vira o gate "nada registrado fora do contrato" no CI.

Registrado no repositório: `tests/Contract/AppInfoContractTest.php` (caso real + as duas reprovações propositais),
`phpunit.xml` (extensão de cobertura) e `config/gesso.php`.

## 2. Teste de arquitetura (ADR 0005)

Regras: `arquitetura-alvo.md`, seção "Estrutura".

| | Pest `arch()` | PHPat 0.12.5 |
|---|---|---|
| Regra "controller não toca banco/Eloquent/HTTP" | escrita de primeira, 1 linha de namespace com `*` | exigiu `Selector::AllOf` (não há `and()`) e **não reprovou** o controller de prova (`DB::table(...)` via facade): ou limitação de dependência estática, ou erro meu de configuração (não resolvido) |
| Como roda | `pest` (já no CI) | como extensão do PHPStan: mistura análise estática e arquitetura |
| Prova de que protege | `specs/tools/prova-arch.sh` injeta o controller que viola e **exige falha**: `Expecting 'App\Features\*\Http\Controllers' not to use 'Illuminate\Support\Facades\DB'` | n/a |
| Limite encontrado | namespace sem arquivo dá `DirectoryNotFoundException` (a regra `Models` só entra com o primeiro Model, Fase 3) | — |

**Resultado: Pest `arch()`.** Nove regras no CI: `strict_types`, controller sem banco/Eloquent/HTTP, `Shared` sem
feature, `Queries`/`Actions` sem HTTP, `env()` só em `config/`, sem `dd`/`dump`, exceções de feature estendem
`ApiException`, controllers `final` e invocáveis. Reconheço que o PHPat só foi avaliado no tempo curto desta fase.

## 3. DTO (ADR 0005)

`StoreVideoData` (5 campos, um com `channel_url` → `channelUrl`) escrito das duas formas, Larastan nível 9 limpo nas duas.

| | `readonly` próprio | `spatie/laravel-data` 4.23 |
|---|---|---|
| Dependências novas | 0 | 3 pacotes (`laravel-data`, `laravel-package-tools`, `php-structure-discoverer`) |
| Peso do `vendor` | — | +3,0 MB (139,5 → 142,5 MB, com dev) |
| Validação | o Form Request já valida e dá o 422 (ADR 0008) | segunda camada de validação, duplicada com o Form Request |
| Mapeamento | `fromValidated(array)` explícito, 1 linha por campo | atributo `#[MapInputName]`, mágico |

**Resultado: `readonly` próprio.** O contrato tem 3 corpos de escrita pequenos; o ganho do pacote (mapeamento e
validação declarativos) duplica o Form Request e adiciona descoberta de classes ao boot do Lambda.

## 4. Scramble (ADR 0007)

`dedoc/scramble` 0.13.47 instalado como dependência de desenvolvimento, `api_path = v1`. O `scramble:export` gera
OpenAPI **3.1** e inferiu `GET /` corretamente (`appname: string`). Sem configuração, exporta `paths` **vazio**: o padrão
`api_path = api` não casa com o prefixo `/v1` (armadilha de 2 minutos).

**Resultado: manter só como detector local**, fora do CI: gera `required` que o YAML não declara e 3.1 contra 3.0.3, então
o diff bruto é ruidoso. As rotas `/docs/api` e `/docs/api.json` do Scramble só existem com a dependência de
desenvolvimento instalada (não vão para produção). A UI oficial do contrato é `/docs`, que serve o YAML-fonte.

## 5. Achados fora do escopo que a 2a encontrou

- **Migrations da Fase 1 não eram idempotentes.** `migrate:fresh` apaga tabelas e **não** tipos nem funções: a 2ª rodada
  quebrava com `function "norm_text" already exists` e `type "dev_reaction_type" already exists`. É exatamente o ciclo
  do G3 (recriar o banco antes de cada rodada). Corrigido: `create or replace function` e `create type` dentro de
  `do $$ ... if not exists ... $$`. Coberto por `ParityDatasetSeederTest` (`RefreshDatabase`) e por `migrate:fresh` duas vezes.
- **`php artisan serve` descarta variáveis de ambiente do container** e relê o `.env` (porta 54320 do host dentro do
  container). O `docker-compose.yml` usa `php -S ... public/index.php`.
- **Diretório vazio não vai para o Git**: o CI reproduzido falhou (`tests/Unit not found`) até haver um teste unitário.
- **Arquivos criados por container ficam do `root`**: `run.sh` roda com `-u $(id -u):$(id -g)`.
