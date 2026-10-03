# 0008 — Erros tipados e formato do contrato

- **Status**: **aceita** pelo usuário em 2026-10-03 (evidência: `ErrorRenderer` e testes de erro)
- **Contexto**: o v1 montava o corpo de erro à mão em cada controller. O inventário está em `../erros-v1.md`.
- **Decisão**: exceções tipadas por feature, renderizadas em um ponto (`bootstrap/app.php`), preservando os
  formatos públicos (`{error}` e `{errorMessage, ...}`). Implementado em `App\Shared\Http\ErrorRenderer`; base `ApiException`
  (`status()`, `body()`); 404, 405, 422 (`{error, errors}`), 500 sem stack trace nem mensagem interna e 503 sem a causa (a causa fica no log, com `request_id`). Erro de infraestrutura = 503; erro de dado = 4xx.
- **Alternativas**: padronizar tudo num formato único (quebra o contrato público).
- **Custo**: dois formatos de erro convivem por herança do contrato.
- **Critério de reversão**: o contrato de erro do OpenAPI se mostrar inconsistente a ponto de pedir mudança aprovada.
