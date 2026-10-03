#!/usr/bin/env node
// Normalizador do diff do G3 (regra 2 de ../dataset-de-paridade.md). Sem dependências.
//
// Entrada: arquivo JSON com a lista de respostas capturadas de UM lado (v1 ou php-laravel):
//   [{ "name": "GET /devs", "status": 200, "body": {...} }, ...]
// Saída: a mesma lista normalizada, para o `diff` comparar byte a byte.
//
// O que a normalização faz (e só isso):
//  - ids (`_id`, `channel_id`, ids em arrays) viram a CHAVE NATURAL: `user` do dev, `name` do canal, id do YouTube
//    do vídeo. O v1 devolve inteiros e o php-laravel UUIDv7 em string (D-7, D-10): não comparam por valor;
//  - `page` e `totalPages` somem (só existem no php-laravel, D-11);
//  - `createdAt` e `updatedAt` somem (o v1 usa `+00:00`; o instante já é coberto pela ordem das listas);
//  - chaves de objeto em ordem alfabética (a ordem de chaves não faz parte do contrato);
//  - arrays de ids de reação (`likes`, `deslikes`, `follow`, `ignore`) em ordem alfabética (são conjuntos).
// Ordem de listas de itens (feed, páginas) é preservada: ela É contrato.
//
// Uso: node normalize-g3.cjs <capturas.json>            imprime o JSON normalizado
//      node normalize-g3.cjs <v1.json> <laravel.json>    exit 0 se iguais, 1 com a primeira diferença
'use strict';
const fs = require('fs');

const DROP = new Set(['page', 'totalPages', 'createdAt', 'updatedAt']);
// Campo -> registro de onde vem a chave natural (por contexto: ids inteiros de entidades diferentes colidem).
const DEV_FIELDS = { likes: 'dev', deslikes: 'dev', follow: 'channel', ignore: 'channel' };
const CHANNEL_FIELDS = { likes: 'dev', deslikes: 'dev' };

const isObj = (v) => v !== null && typeof v === 'object' && !Array.isArray(v);
const youtubeId = (url) => (typeof url === 'string' && /[?&]v=([^&]+)/.exec(url) ? /[?&]v=([^&]+)/.exec(url)[1] : null);

function kindOf(o) {
  if (!isObj(o) || o._id === undefined) return null;
  if ('user' in o && 'follow' in o) return 'dev';
  if ('link' in o && 'category' in o && 'name' in o) return 'channel';
  if ('title' in o && 'url' in o && 'channel_id' in o) return 'video';
  return null;
}

function walk(v, fn) {
  if (Array.isArray(v)) v.forEach((x) => walk(x, fn));
  else if (isObj(v)) { fn(v); Object.values(v).forEach((x) => walk(x, fn)); }
}

function buildRegistries(captures) {
  const reg = { dev: new Map(), channel: new Map(), video: new Map() };
  for (const c of captures) {
    walk(c.body, (o) => {
      const kind = kindOf(o);
      if (kind === 'dev') reg.dev.set(String(o._id), o.user);
      if (kind === 'channel') reg.channel.set(String(o._id), o.name);
      if (kind === 'video') reg.video.set(String(o._id), youtubeId(o.url) ?? o.title);
    });
  }
  return reg;
}

function lookup(reg, kind, id) {
  const key = reg[kind].get(String(id));
  if (key === undefined) throw new Error(`id sem chave natural (${kind}): ${id}. Capture a listagem que o contém antes.`);
  return key;
}

function normalizeValue(v, reg) {
  if (Array.isArray(v)) return v.map((x) => normalizeValue(x, reg));
  if (!isObj(v)) return v;
  const kind = kindOf(v);
  const fields = kind === 'dev' ? DEV_FIELDS : kind === 'channel' ? CHANNEL_FIELDS : {};
  const out = {};
  for (const k of Object.keys(v).sort()) {
    if (DROP.has(k)) continue;
    const val = v[k];
    if (k === '_id' && kind) out[k] = lookup(reg, kind, val);
    else if (k === 'channel_id' && kind === 'video') out[k] = lookup(reg, 'channel', val);
    else if (fields[k] && Array.isArray(val)) out[k] = val.map((id) => lookup(reg, fields[k], id)).sort();
    else out[k] = normalizeValue(val, reg);
  }
  return out;
}

function normalize(captures) {
  const reg = buildRegistries(captures);
  return captures.map((c) => ({ name: c.name, status: c.status, body: normalizeValue(c.body, reg) }));
}

function firstDifference(a, b, path = '$') {
  if (typeof a !== typeof b || Array.isArray(a) !== Array.isArray(b)) return `${path}: tipo diferente`;
  if (Array.isArray(a)) {
    if (a.length !== b.length) return `${path}: tamanho ${a.length} != ${b.length}`;
    for (let i = 0; i < a.length; i++) { const d = firstDifference(a[i], b[i], `${path}[${i}]`); if (d) return d; }
    return null;
  }
  if (isObj(a)) {
    const ka = Object.keys(a); const kb = Object.keys(b);
    for (const k of new Set([...ka, ...kb])) {
      if (!(k in a)) return `${path}.${k}: só no segundo`;
      if (!(k in b)) return `${path}.${k}: só no primeiro`;
      const d = firstDifference(a[k], b[k], `${path}.${k}`); if (d) return d;
    }
    return null;
  }
  return a === b ? null : `${path}: ${JSON.stringify(a)} != ${JSON.stringify(b)}`;
}

module.exports = { normalize, firstDifference };

if (require.main === module) {
  const files = process.argv.slice(2);
  const load = (f) => normalize(JSON.parse(fs.readFileSync(f, 'utf8')));
  if (files.length === 1) { process.stdout.write(`${JSON.stringify(load(files[0]), null, 2)}\n`); process.exit(0); }
  if (files.length === 2) {
    const diff = firstDifference(load(files[0]), load(files[1]));
    if (diff) { console.error(`DIFERE: ${diff}`); process.exit(1); }
    console.log('IGUAIS após a normalização');
    process.exit(0);
  }
  console.error('uso: normalize-g3.cjs <capturas.json> | <v1.json> <laravel.json>');
  process.exit(2);
}
