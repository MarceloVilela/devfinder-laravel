# S5 e S6 — achados parciais colhidos durante o spike (2026-10-03)

Pertencem à Fase 2b e **não foram executados por completo**; o que apareceu fica registrado.

## S5 — deploy sem chave de longa duração e sem login do Serverless
- O `osls` (oss-serverless 4.4.0, indicado pelo stub do Bref 3) fez **dois deploys e duas remoções sem login nem chave** do Serverless
  Framework. Resolve a dúvida sobre o v4 exigir conta própria.
- **OIDC GitHub→AWS não foi testado.** O spike usou credenciais locais de um usuário IAM com AdministratorAccess. O CI precisa de uma
  role de escopo mínimo; a lista exata de permissões ainda não foi levantada.

## S6 — Function URL, concorrência reservada e custo
- **Limite de concorrência da conta na região: 10** (10 sem reserva). **Não dá para reservar concorrência** para a função (a AWS
  exige manter um mínimo sem reserva). A defesa prevista no plano **não existe** nesta conta.
- O próprio limite de 10 vira o teto de custo e de conexões abertas ao Neon, mas também de tráfego legítimo (por região).
- A Function URL ficou com auth `NONE`, sem throttling próprio e sem CORS.
- **Custo** de 1 a 3 de outubro no Cost Explorer: **US$ 0,00** em todos os serviços (estimado; o Cost Explorer atrasa horas, então a
  janela de hoje ainda não está fechada). Dois deploys e duas remoções, ~330 invocações do S4 e ~60 do S7.
