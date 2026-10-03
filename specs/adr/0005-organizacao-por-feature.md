# 0005 — Organização por feature e Actions

- **Status**: **aceita** pelo usuário em 2026-10-03 (evidência: `specs/spikes/2a-ferramentas.md`)
- **Contexto**: o v1 organizava por camada técnica e deixava regra, HTTP externo e corpo de erro no controller.
- **Decisão**: `app/Features/<Feature>/{Actions,Data,Http,Models,Policies,Queries,Exceptions}`; DTOs
  `readonly`; controller só orquestra. **Action onde houver regra, escrita ou integração**; leitura
  sem regra pode chamar `Queries/` direto do controller (evita Action que só repassa).
  **DTO**: classe `readonly` própria, criada de `$request->validated()` (sem `spatie/laravel-data`: 3 pacotes, +3 MB, validação duplicada com o Form Request).
  **Teste de arquitetura**: Pest `arch()`, 9 regras no CI (`tests/Arch/ArchitectureTest.php`), com prova de falha (`specs/tools/prova-arch.sh`).
- **Alternativas**: camadas técnicas padrão do Laravel; Services genéricos; DTO com `spatie/laravel-data`; PHPat (avaliado só no tempo curto da 2a: não reprovou o controller de prova).
- **Custo**: cerimônia em rotas simples (mitigada pela exceção de leitura).
- **Critério de reversão**: o teste de arquitetura da Fase 2a mostrar cerimônia sem valor mesmo com a exceção.
