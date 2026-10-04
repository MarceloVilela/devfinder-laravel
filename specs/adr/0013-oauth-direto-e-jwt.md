# 0013 — OAuth do GitHub direto e JWT próprio

- **Status**: **aceita** pelo usuário em 2026-10-04, **com evidência parcial**: fluxo completo provado com `GithubClient` falso (testes, G3 e simulação de produção em `specs/execucao-fase-4.log`); o login real com o OAuth App fica para o aceite da fase.
- **Contexto**: o contrato manda `GET /auth/github`, `GET /auth/github/callback` (redirect ao front com `?token=`) e Bearer JWT `{username}` de 7 dias.
  O v1 não tinha `state` (login CSRF) nem tratamento de falha do GitHub (500), e a chamada ao GitHub ficava dentro do controller (A7).
  O Lambda não tem sessão nem memória entre requisições.
- **Decisão**:
  1. **OAuth direto** com o cliente HTTP do Laravel atrás da interface `GithubClient` (timeout 5 s, 1 retry só em erro de conexão); sem Socialite.
  2. **`state` ligado ao navegador, sem armazenamento no servidor**: 32 bytes aleatórios no `state` e num cookie `HttpOnly; SameSite=Lax; Path=/v1/auth` de 10 min; o callback compara os dois com `hash_equals`.
  3. **JWT HS256** com `firebase/php-jwt`, algoritmo fixado, `exp` obrigatório, segredo de no mínimo 32 bytes no SSM; payload `{username, iat, exp}`.
  4. **Middleware `auth` (obrigatório) e `auth.optional`**, com o Dev carregado em 1 query e entregue como `AuthenticatedDev` (`Shared`).
  5. **Rate limiting** só em `/auth/github` e no callback, 10 por minuto por IP, no store `database` (ADR 0011).
- **Alternativas**: Socialite (dependência maior, esconde o `state` e o tratamento de erro que queremos testar; o v1 já usava o fluxo direto);
  `state` guardado no cache em tabela (uma escrita no Neon por início de login, e não prende o `state` ao navegador);
  `state` assinado sem cookie (não prende ao navegador); implementar HS256 à mão (mais código a auditar que a lib); RS256 (chave e rotação sem ganho num monólito).
- **Custo**: 1 dependência (`firebase/php-jwt`, ~30 KB); cada login toca o Neon (upsert) e o limiter (3 a 6 queries, S7).
- **Limites conhecidos**: o token vai na URL do redirect ao front (o contrato manda); mitigado com `Cache-Control: no-store` e `Referrer-Policy: no-referrer`, mas fica no histórico do navegador.
  Sem revogação nem refresh: o token vale 7 dias, a menos que o segredo mude. O cookie `httpOnly` de sessão do original segue fora do escopo (A4).
  `?user=` em `GET /feed/trending` identifica o dev sem token (paridade com o original) e deixa qualquer um ver o feed personalizado de qualquer dev.
- **Critério de reversão**: falha de segurança na lib ou necessidade de revogar tokens: sessão em tabela ou tokens opacos com lista de revogação, por nova ADR.
