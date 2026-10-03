# 0010 — Regra de custo reformulada

- **Status**: **aceita** pelo usuário em 2026-10-03 (evidência parcial: custo US$ 0,00 de 1 a 3 de outubro, `specs/spikes/s5-s6-achados-parciais.md`)
- **Contexto**: a regra do v1 exigia camada "genuinamente sempre-gratuita". O Free Plan atual da AWS
  (crédito por 6 meses) **encerra a conta** ao expirar (`../serverless/specs/aws-pending__.md`).
- **Decisão**: custo zero **durante o Free Plan**, com decisão de fim de vida (upgrade ou teardown) até o dia 150.
  Allowlist de recursos no CI; Budgets com alerta em US$ 1.
- **Alternativas**: manter só hospedagem sempre-gratuita fora da AWS (descarta o objetivo de rodar na AWS).
- **Custo**: a API pode sumir se a decisão de fim de vida não for tomada.
- **Critério de reversão**: AWS mudar a regra do Free Plan (rever a ADR).
