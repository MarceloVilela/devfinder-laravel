#!/usr/bin/env node
// G3 da ingestão (Fase 6): manda os MESMOS candidatos a `POST /video/refresh` do v1 local e do php-laravel local e grava as respostas para o
// `normalize-g3.cjs`. O `video:refresh` do v1 só lê o JSONBin real (sem fixture), e a rota usa o mesmo `VideoIngestor`; é por ela que os dois lados
// recebem a mesma entrada. Os candidatos vêm da fixture congelada `tests/Fixtures/jsonbin-videos.json` (formato do bin: `channel_name` vira `channel`).
// Uso: G3_TOKEN=<jwt do dev01 do próprio lado> node capture-g3-ingest.cjs <baseUrl com /v1> <saida.json> [fixture.json]
// ANTES no php-laravel: `update devs set role = 'ADMIN' where username = 'dev01'` (o v1 não tem papéis). Altera o banco: recriar os dois antes.
'use strict';
const fs = require('fs');

const [base, out, fixture = `${__dirname}/../../tests/Fixtures/jsonbin-videos.json`] = process.argv.slice(2);
const token = process.env.G3_TOKEN;
if (!base || !out || !token) { console.error('uso: G3_TOKEN=<jwt> capture-g3-ingest.cjs <baseUrl> <saida.json> [fixture]'); process.exit(2); }

const bin = JSON.parse(fs.readFileSync(fixture, 'utf8'));
const record = bin.record.map((i) => ({ title: i.title, url: i.url, channel: i.channel_name, channel_url: i.channel_url, thumbnail: i.thumbnail }));

const STEPS = [
  ['GET', '/feed/trending?page=1'], ['GET', '/channels'], ['GET', '/devs?page=1'],
  ['POST', '/video/refresh', { record }], ['POST', '/video/refresh', { record }], // a 2ª prova a idempotência
  ['POST', '/video/refresh', {}], ['POST', '/video/refresh', { record: [] }],
  ['GET', '/feed/trending?page=1'], ['GET', '/video/INGESTNEW01'],
];

(async () => {
  const captures = [];
  for (const [method, path, body] of STEPS) {
    const headers = { Accept: '*/*', ...(body ? { 'Content-Type': 'application/json' } : {}), ...(method === 'POST' ? { Authorization: `Bearer ${token}` } : {}) };
    const res = await fetch(base + path, { method, headers, body: body ? JSON.stringify(body) : undefined });
    const text = await res.text();
    const type = res.headers.get('content-type') ?? '';
    captures.push({ name: `${method} ${path}${body ? ` #${captures.length}` : ''}`, status: res.status, contentType: type.split(';')[0], body: type.includes('json') ? JSON.parse(text) : text });
  }
  fs.writeFileSync(out, `${JSON.stringify(captures, null, 2)}\n`);
  console.log(`${captures.length} capturas em ${out}`);
})().catch((e) => { console.error(e); process.exit(1); });
