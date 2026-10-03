#!/usr/bin/env node
// Captura as rotas públicas de leitura (anônimas) de UM lado do G3 e grava o JSON que o `normalize-g3.cjs` compara.
// Uso: node capture-g3.cjs <baseUrl com /v1> <saida.json>
// Só GET, sem token: não altera nada no servidor. Os casos espelham `specs/acceptance/{app,devs,channels,videos,description}.http`.
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
  fs.writeFileSync(out, `${JSON.stringify(captures, null, 2)}\n`);
  console.log(`${captures.length} capturas em ${out}`);
}
main().catch((e) => { console.error(e); process.exit(1); });
