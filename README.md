# php-laravel

API do DevFinder em **Laravel + PostgreSQL (Neon) em AWS Lambda via Bref**, com o mesmo contrato público de
`devfinder-api` e custo zero. Reescrita do `php-codei` (CodeIgniter 4 + MySQL).

**Estado**: Fase 0 (especificação) em andamento. Ainda não há código da aplicação, deploy nem demo.

- Plano: [`plan.md`](./plan.md)
- Estado das specs: [`specs/README.md`](./specs/README.md)
- Contrato: [`specs/fase-0-openapi.yaml`](./specs/fase-0-openapi.yaml)

## Limitações conhecidas (até agora)

- Nenhuma medição de latência, cold start ou custo foi feita.
- O oráculo de paridade (v1) cobre 27 das 30 operações do contrato.
- O Free Plan da AWS encerra a conta ao expirar; a decisão de fim de vida ainda não foi tomada.
