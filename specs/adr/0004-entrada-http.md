# 0004 — Entrada HTTP: Lambda Function URL

- **Status**: proposta; evidência parcial do S6 (`specs/spikes/s5-s6-fase-2b.md`): aceita pelo usuário **depois do deploy real** e da medição de latência
- **Contexto**: o API Gateway REST não é gratuito na conta; o HTTP API custa US$ 1 por milhão de requisições.
- **Decisão**: Function URL, com rate limiting e concorrência reservada como defesa.
- **Alternativas**: HTTP API (cobra do crédito); API Gateway REST.
- **Custo**: sem adicional. Sem throttling nativo e sem WAF. **Medido (S6)**: o limite de concorrência da conta é 10 e **não permite reservar**, então não há defesa de infraestrutura contra abuso volumétrico; sobram o limite de 10 (teto de custo e de conexões no Neon), o rate limiting da ADR 0011 e o alarme de custo. Vai para o README como limitação.
- **Critério de reversão**: o S6/S7 mostrarem que a defesa da aplicação não cobre o abuso, ou a conta não permitir concorrência reservada.
