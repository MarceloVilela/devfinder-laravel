# 0005 — Organização por feature e Actions

- **Status**: proposta
- **Contexto**: o v1 organizava por camada técnica e deixava regra, HTTP externo e corpo de erro no controller.
- **Decisão**: `app/Features/<Feature>/{Actions,Data,Http,Models,Policies,Queries,Exceptions}`; DTOs
  `readonly`; controller só orquestra. **Action onde houver regra, escrita ou integração**; leitura
  sem regra pode chamar `Queries/` direto do controller (evita Action que só repassa).
- **Alternativas**: camadas técnicas padrão do Laravel; Services genéricos.
- **Custo**: cerimônia em rotas simples (mitigada pela exceção de leitura).
- **Critério de reversão**: o teste de arquitetura da Fase 2a mostrar cerimônia sem valor mesmo com a exceção.
