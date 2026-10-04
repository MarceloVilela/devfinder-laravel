#!/usr/bin/env node
// Captura a sequência de ESCRITA do G3 de UM lado (v1 local ou php-laravel local) e grava o JSON que o `normalize-g3.cjs` compara.
// Uso: G3_TOKEN=<jwt do dev01 do próprio lado> node capture-g3-write.cjs <baseUrl com /v1> <saida.json>
// ANTES de rodar no php-laravel: `update devs set role = 'ADMIN' where username = 'dev01'` (F5-15: canal e vídeo só para ADMIN; o v1 não tem papéis).
// ATENÇÃO: altera o banco. Recriar os dois bancos (migrate:refresh + seed) antes de cada rodada e rodar a MESMA ordem nos dois lados.
// Nunca apontar para o Render do v1 nem para o deploy real (regra do CLAUDE.md). Os casos com `userGithub` e `octocat` usam o GitHub real.
// Fora da sequência: POST /devs com username inexistente no GitHub (divergência aprovada D-6: o v1 devolve 500, aqui 404) e
// GET /feed/subscriptions (o v1 não tem a rota, D-5; validada contra o contrato e nos testes).
'use strict';
const fs = require('fs');

const TOKEN = process.env.G3_TOKEN;
const J = { 'Content-Type': 'application/json' };

// [método, caminho, corpo, quem]  quem: 'dev01' (token), null (sem token)
const STEPS = [
  // leituras que alimentam o registro de chaves naturais (ids do v1 e UUIDs não comparam por valor)
  ['GET', '/devs?page=1'], ['GET', '/devs?page=2'], ['GET', '/channels'], ['GET', '/feed/trending?page=1'], ['GET', '/feed/trending?page=2'],
  // reações entre devs
  ['POST', '/likes/devs/no-such-dev-zzz', null, 'dev01'], ['POST', '/likes/devs/dev04', null, 'dev01'], ['POST', '/likes/devs/dev04', null, 'dev01'],
  ['POST', '/likes/devs/dev01', null, 'dev01'], ['GET', '/likes/devs', null, 'dev01'], ['DELETE', '/likes/devs/dev04', null, 'dev01'],
  ['DELETE', '/likes/devs/dev04', null, 'dev01'], ['GET', '/likes/devs', null, 'dev01'],
  ['POST', '/dislikes/devs/no-such-dev-zzz', null, 'dev01'], ['POST', '/dislikes/devs/dev05', null, 'dev01'], ['GET', '/dislikes/devs', null, 'dev01'],
  ['DELETE', '/dislikes/devs/dev05', null, 'dev01'],
  // reações em canais
  ['POST', '/likes/channels/no-such-channel-zzz', null, 'dev01'], ['POST', '/likes/channels/Canal%20Zeta', null, 'dev01'],
  ['DELETE', '/likes/channels/Canal%20Zeta', null, 'dev01'], ['POST', '/dislikes/channels/no-such-channel-zzz', null, 'dev01'],
  ['POST', '/dislikes/channels/Canal%20Zeta', null, 'dev01'], ['DELETE', '/dislikes/channels/Canal%20Zeta', null, 'dev01'],
  // vídeo
  ['POST', '/video', { title: 'Video de teste', url: 'https://www.youtube.com/watch?v=zzzTESTID001', channel: 'Canal Que Nao Existe', channel_url: 'https://www.youtube.com/channel/UCNAOEXISTE' }, 'dev01'],
  ['POST', '/video', { title: 'Video de teste Fase 5', url: 'https://www.youtube.com/watch?v=zzzTESTID001&pp=abc', channel: 'Canal Alpha', channel_url: 'https://youtube.com/alpha' }, 'dev01'],
  ['POST', '/video', { title: 'Video de teste Fase 5 duplicado', url: 'https://www.youtube.com/watch?v=zzzTESTID001', channel: 'Canal Alpha', channel_url: 'https://youtube.com/alpha' }, 'dev01'],
  // canal
  ['POST', '/channels', { link: 'https://www.youtube.com/channel/UCFASE5TEST', title: 'Canal Fase5 Teste', description: 'desc original', tags: ['fase5'], category: 'Fase5 Design 🎨' }, 'dev01'],
  ['POST', '/channels', { link: 'https://www.youtube.com/channel/UCFASE5TEST', title: 'Canal Fase5 Teste', description: 'desc atualizada', tags: ['fase5', 'atualizado'], category: 'Categoria Nova' }, 'dev01'],
  ['POST', '/channels', { link: 'https://www.youtube.com/channel/UCFASE5TEST2', title: 'Canal Fase5 Com Dev', category: 'Tech', userGithub: 'octocat' }, 'dev01'],
  // dev
  ['POST', '/devs', { username: 'dev01' }, null], ['POST', '/devs', { username: 'dev01' }, 'dev01'], ['POST', '/devs', { username: 'octocat' }, 'dev01'],
  // resultado final: reações, assinaturas e listagens com as escritas aplicadas
  ['POST', '/likes/channels/Canal%20Beta', null, 'dev01'],
  ['GET', '/devs?page=1'], ['GET', '/devs?page=2'], ['GET', '/channels'], ['GET', '/feed/trending?page=1'], ['GET', '/video/zzzTESTID001'],
];

async function main() {
  const [base, out] = process.argv.slice(2);
  if (!base || !out || !TOKEN) { console.error('uso: G3_TOKEN=<jwt> capture-g3-write.cjs <baseUrl> <saida.json>'); process.exit(2); }
  const captures = [];
  for (const [method, path, body, who] of STEPS) {
    const headers = { Accept: '*/*', ...(body ? J : {}) };
    if (who === 'dev01') headers.Authorization = `Bearer ${TOKEN}`;
    const res = await fetch(base + path, { method, headers, body: body ? JSON.stringify(body) : undefined });
    const text = await res.text();
    const type = res.headers.get('content-type') ?? '';
    let parsed = text;
    if (type.includes('json')) { try { parsed = JSON.parse(text); } catch { parsed = text; } }
    captures.push({ name: `${method} ${path}${who ? ' (dev01)' : ''}${body ? ' ' + JSON.stringify(body).slice(0, 60) : ''}`, status: res.status, contentType: type.split(';')[0], body: parsed });
  }
  fs.writeFileSync(out, `${JSON.stringify(captures, null, 2)}\n`);
  console.log(`${captures.length} capturas em ${out}`);
}
main().catch((e) => { console.error(e); process.exit(1); });
