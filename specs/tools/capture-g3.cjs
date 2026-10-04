#!/usr/bin/env node
// Captura as rotas públicas de leitura (anônimas) de UM lado do G3 e grava o JSON que o `normalize-g3.cjs` compara.
// Uso: node capture-g3.cjs <baseUrl com /v1> <saida.json>
// Só GET: não altera nada no servidor. Sem `G3_TOKEN` captura só o caminho anônimo; com `G3_TOKEN` (JWT de `dev01` do
// próprio lado: o segredo difere entre o v1 e o php-laravel) captura também `/me` e a personalização (Fase 4). Os casos espelham `specs/acceptance/{app,devs,channels,videos,description}.http`.
'use strict';
const fs = require('fs');

const CASES = [
  '/',
  '/devs?page=1', '/devs?page=2', '/devs',
  '/devs?page=0', '/devs?page=-1', '/devs?page=abc', '/devs?page=1.5', '/devs?page=3', '/devs?page=999',
  '/devs/dev01', '/devs/dev02', '/devs/dev03', '/devs/nobody',
  '/channels', '/channels/Canal%20Alpha', '/channels/Canal%20Beta', '/channels/nao-existe',
  '/description/feed', '/description/category',
  '/feed/trending?page=1', '/feed/trending?page=2', '/feed/trending?page=99',
  '/feed/channel?channel_name=Canal%20Beta&page=1', '/feed/channel?channel_name=Canal%20Beta&page=2',
  '/feed/channel?channel_name=Canal%20Alpha', '/feed/channel?channel_name=NaoExiste',
  '/video/vidalpha01', '/video/vidbeta35', '/video/does-not-exist',
];

// Caminho + cabeçalho: token do `dev01` (reações conhecidas), de um dev sem reações e sem token.
const AUTH_CASES = [
  ['/me', 'dev01'], ['/me', null], ['/me', 'garbage'],
  ['/devs?page=1', 'dev01'], ['/devs?page=2', 'dev01'], ['/devs?page=1', 'garbage'],
  ['/feed/trending?page=1', 'dev01'], ['/feed/trending?page=2', 'dev01'], ['/feed/trending?page=1&user=dev01', null],
  ['/feed/trending?page=1&user=fantasma', null],
];

async function main() {
  const [base, out] = process.argv.slice(2);
  if (!base || !out) { console.error('uso: capture-g3.cjs <baseUrl> <saida.json>'); process.exit(2); }
  const captures = [];
  for (const path of CASES) {
    const res = await fetch(base + path, { headers: { Accept: '*/*' } });
    const text = await res.text();
    const type = res.headers.get('content-type') ?? '';
    captures.push({ name: `GET ${path}`, status: res.status, contentType: type.split(';')[0], body: type.includes('json') ? JSON.parse(text) : text });
  }
  if (process.env.G3_TOKEN) {
    for (const [path, who] of AUTH_CASES) {
      const headers = { Accept: '*/*' };
      if (who === 'dev01') headers.Authorization = `Bearer ${process.env.G3_TOKEN}`;
      if (who === 'garbage') headers.Authorization = 'Bearer garbage.invalid.token';
      const res = await fetch(base + path, { headers });
      const text = await res.text();
      const type = res.headers.get('content-type') ?? '';
      captures.push({ name: `AUTH(${who ?? 'anonimo'}) GET ${path}`, status: res.status, contentType: type.split(';')[0], body: type.includes('json') ? JSON.parse(text) : text });
    }
  }
  fs.writeFileSync(out, `${JSON.stringify(captures, null, 2)}\n`);
  console.log(`${captures.length} capturas em ${out}`);
}
main().catch((e) => { console.error(e); process.exit(1); });
