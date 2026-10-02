# 0003 — AWS Lambda + Bref

- **Status**: proposta
- **Contexto**: o objetivo é rodar na AWS dentro do crédito do Free Plan, sem hospedagem fixa.
- **Decisão**: Lambda com Bref (camada PHP-FPM), fora de VPC, deploy por Serverless Framework (a confirmar no S5).
- **Alternativas**: container no Render (v1); Laravel Vapor (pago); Laravel Octane em VM (custo fixo).
- **Custo**: Always Free do Lambda; CloudWatch com retenção curta.
- **Critério de reversão**: custo real maior que o previsto (S6) ou cold start fora do G1.
