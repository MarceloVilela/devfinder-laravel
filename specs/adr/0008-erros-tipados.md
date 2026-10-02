# 0008 — Erros tipados e formato do contrato

- **Status**: proposta
- **Contexto**: o v1 montava o corpo de erro à mão em cada controller. O inventário está em `../erros-v1.md`.
- **Decisão**: exceções tipadas por feature, renderizadas em um ponto (`bootstrap/app.php`), preservando os
  formatos públicos (`{error}` e `{errorMessage, ...}`). Erro de infraestrutura = 503; erro de dado = 4xx.
- **Alternativas**: padronizar tudo num formato único (quebra o contrato público).
- **Custo**: dois formatos de erro convivem por herança do contrato.
- **Critério de reversão**: o contrato de erro do OpenAPI se mostrar inconsistente a ponto de pedir mudança aprovada.
