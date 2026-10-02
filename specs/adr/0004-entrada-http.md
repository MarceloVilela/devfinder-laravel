# 0004 — Entrada HTTP: Lambda Function URL

- **Status**: proposta (hipótese, decidida no S6)
- **Contexto**: o API Gateway REST não é gratuito na conta; o HTTP API custa US$ 1 por milhão de requisições.
- **Decisão**: Function URL, com rate limiting e concorrência reservada como defesa.
- **Alternativas**: HTTP API (cobra do crédito); API Gateway REST.
- **Custo**: sem adicional. Sem throttling nativo e sem WAF.
- **Critério de reversão**: o S6/S7 mostrarem que a defesa da aplicação não cobre o abuso, ou a conta não permitir concorrência reservada.
