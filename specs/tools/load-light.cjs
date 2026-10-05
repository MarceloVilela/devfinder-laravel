#!/usr/bin/env node
// Carga leve e honesta (Fase 7.3): N requisições GET com concorrência C, conexão nova a cada requisição (`Connection: close`, como o curl), e percentis.
// Escrito à mão porque o `autocannon` não completa nenhuma requisição contra o servidor embutido do PHP (`php -S`) usado no ambiente local.
// Uso: node load-light.cjs <url> [requisições=200] [concorrência=4]   Só leitura (GET). Nunca contra o deploy real com concorrência acima de 5 (a conta tem limite 10).
'use strict';
const http = require('http');
const https = require('https');

const [url, total = '200', conc = '4'] = process.argv.slice(2);
if (!url) { console.error('uso: load-light.cjs <url> [requisições] [concorrência]'); process.exit(2); }
const lib = url.startsWith('https') ? https : http;
const N = Number(total); const C = Math.min(Number(conc), 5);

const one = () => new Promise((resolve) => {
  const t0 = process.hrtime.bigint();
  const req = lib.get(url, { headers: { Connection: 'close', Accept: 'application/json' }, agent: false }, (res) => {
    res.resume();
    res.on('end', () => resolve({ ms: Number(process.hrtime.bigint() - t0) / 1e6, status: res.statusCode }));
  });
  req.on('error', () => resolve({ ms: Number(process.hrtime.bigint() - t0) / 1e6, status: 0 }));
  req.setTimeout(30000, () => req.destroy());
});

(async () => {
  const results = []; let next = 0;
  const started = Date.now();
  await Promise.all(Array.from({ length: C }, async () => { while (next++ < N) results.push(await one()); }));
  const secs = (Date.now() - started) / 1000;
  const ms = results.map((r) => r.ms).sort((a, b) => a - b);
  const pct = (p) => ms[Math.min(ms.length - 1, Math.floor((p / 100) * ms.length))];
  const bad = results.filter((r) => r.status < 200 || r.status >= 300).length;
  console.log(JSON.stringify({ url: url.replace(/^(https?:\/\/)[^/]+/, '$1<host>'), requests: results.length, concurrency: C, seconds: +secs.toFixed(1), rps: +(results.length / secs).toFixed(1),
    p50: +pct(50).toFixed(1), p95: +pct(95).toFixed(1), p99: +pct(99).toFixed(1), max: +ms[ms.length - 1].toFixed(1), non2xx: bad }));
})();
