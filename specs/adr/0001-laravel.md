# 0001 — Laravel e PHP

- **Status**: proposta
- **Contexto**: o v1 usou CodeIgniter 4. Bref tem guia próprio de Laravel e o ecossistema de
  qualidade (Pint, Larastan, Pest) e de contrato de API é maior.
- **Decisão**: Laravel na versão estável vigente; versão exata e PHP fixados na ADR 0009 depois do spike.
- **Alternativas**: manter CodeIgniter 4 (já tem v1); Symfony (sem ganho de portfólio claro).
- **Custo**: framework mais pesado no boot do Lambda (medido no S3).
- **Critério de reversão**: bloqueio técnico nos spikes S1 ou S2, ou cold start fora do G1 sem saída.
