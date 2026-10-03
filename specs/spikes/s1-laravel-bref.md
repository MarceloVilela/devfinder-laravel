# S1 — Laravel + Bref sobem no Lambda com Function URL? (2026-10-03)

**Resultado: PASSA.** `GET /health` respondeu 200 a frio e a quente.

| Item | Medido |
|---|---|
| PHP | **8.4.26** (camada Bref `php-84`, versão 23 em `us-east-2`), runtime `provided.al2023` |
| Laravel | **13.34.0** (instalado por `composer create-project`; exige PHP `^8.3`) |
| Bref | 3.0.12 (`bref/bref`) + `bref/laravel-bridge` 3.1.3; CLI de deploy `osls` 4.4.0 |
| Entrada | Lambda **Function URL** (`url: true`), auth `NONE`, CORS não configurado |
| Pacote | **16,5 MB** zipado (limite de 250 MB descompactado) |
| Função | 1024 MB, timeout 28 s, arquitetura `x86_64`, memória máxima usada 131–135 MB |
| Extensões | `pdo_pgsql` carregada, OPcache ligado, SAPI `fpm-fcgi` |
| Deploy | `osls deploy --stage spike`: **96 s**, stack `devfinder-laravel-spike-spike` em `us-east-2` |

**Recursos criados (9, todos dentro da allowlist):** `AWS::Lambda::Function`, `::Version`, `::Url`, 2× `::Permission`,
`AWS::Logs::LogGroup`, `AWS::IAM::Role`, `AWS::S3::Bucket` e `::BucketPolicy` (bucket de artefatos do Serverless).

**Achados para a Fase 2b**
- O log group nasceu **sem retenção** (nunca expira): definir `logRetentionInDays` (7 a 14) no template.
- Memória usada ~135 MB de 1024 MB: calibrar a memória (CPU do Lambda escala com ela) contra a latência.
- Camadas `arm-php-84` existem; arquitetura `arm64` não foi testada (candidata a custo menor).
- `APP_KEY` e URLs do banco entraram como variáveis de ambiente da função (aceitável só no spike): em produção, SSM (S5).
