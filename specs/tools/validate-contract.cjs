#!/usr/bin/env node
// Compara respostas REAIS de uma API com os schemas de ../fase-0-openapi.yaml, em modo ESTRITO:
//  - propriedade declarada e ausente        -> "ausente"
//  - propriedade não declarada na resposta   -> "extra"
//  - tipo diferente / null sem `nullable`    -> "tipo"
//  - valor fora do `enum` ou data inválida   -> "valor"
// (O OpenAPI não marca `required` nas respostas; o modo estrito trata o schema como o shape exato esperado.)
//
// Uso (precisa de js-yaml: `npm i js-yaml` em qualquer pasta e NODE_PATH apontando para ela):
//   BASE_URL=http://localhost:8081/v1 AUTH_TOKEN=... NODE_PATH=<pasta>/node_modules \
//     node validate-contract.cjs [--writes]
// Sem --writes só faz GET (não altera nada). Com --writes também faz POST/DELETE (altera o banco alvo: só
// contra banco descartável, nunca contra o deploy real do v1).
const fs = require('fs');
const path = require('path');
const yaml = require('js-yaml');

const BASE = process.env.BASE_URL || 'http://localhost:8081/v1';
const TOKEN = process.env.AUTH_TOKEN || '';
const WRITES = process.argv.includes('--writes');
const spec = yaml.load(fs.readFileSync(path.join(__dirname, '..', 'fase-0-openapi.yaml'), 'utf8'));

const resolve = (s) => (s && s.$ref ? resolve(s.$ref.split('/').slice(1).reduce((o, k) => o[k], spec)) : s);
const typeOf = (v) => (v === null ? 'null' : Array.isArray(v) ? 'array' : typeof v === 'number' ? (Number.isInteger(v) ? 'integer' : 'number') : typeof v);
const okType = (exp, v) => {
  const t = typeOf(v);
  if (exp === 'number') return t === 'number' || t === 'integer';
  if (exp === 'integer') return t === 'integer';
  return exp === t;
};

function check(schemaIn, v, p, out) {
  const s = resolve(schemaIn);
  if (!s) return;
  if (v === null) { if (!s.nullable) out.add(`tipo      ${p}: null, mas o contrato não declara nullable`); return; }
  if (s.type && !okType(s.type, v)) { out.add(`tipo      ${p}: contrato=${s.type}, obtido=${typeOf(v)}`); return; }
  if (s.enum && !s.enum.includes(v)) out.add(`valor     ${p}: ${JSON.stringify(v)} fora do enum ${JSON.stringify(s.enum)}`);
  if (s.format === 'date-time' && typeof v === 'string' && !/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(\.\d+)?(Z|[+-]\d\d:\d\d)$/.test(v)) out.add(`valor     ${p}: data não é date-time RFC 3339 (${v})`);
  if (s.type === 'array') { v.forEach((it) => check(s.items, it, `${p}[]`, out)); return; }
  if (s.type === 'object' || s.properties) {
    const props = s.properties || {};
    for (const k of Object.keys(props)) {
      if (!(k in v)) out.add(`ausente   ${p}.${k}`);
      else check(props[k], v[k], `${p}.${k}`, out);
    }
    if (s.properties) for (const k of Object.keys(v)) if (!(k in props)) out.add(`extra     ${p}.${k}`);
  }
}

const stamp = Date.now();
const probes = [
  ['GET', '/', 'public'],
  ['GET', '/devs?page=1', 'public'], ['GET', '/devs?page=1', 'auth'],
  ['GET', '/devs/dev01', 'public'],
  ['GET', '/channels', 'public'], ['GET', '/channels/Canal%20Alpha', 'public'],
  ['GET', '/description/feed', 'public'], ['GET', '/description/category', 'public'],
  ['GET', '/feed/trending?page=1', 'public'], ['GET', '/feed/trending?page=1', 'auth'],
  ['GET', '/feed/channel?channel_name=Canal%20Beta&page=1', 'public'],
  ['GET', '/video/vidalpha01', 'public'],
  ['GET', '/me', 'auth'], ['GET', '/likes/devs', 'auth'], ['GET', '/dislikes/devs', 'auth'],
  ['GET', '/search?q=Alpha', 'public'], ['GET', '/feed/subscriptions?page=1', 'auth'],
];
if (WRITES) probes.push(
  ['POST', '/video', 'auth', { title: `Contrato ${stamp}`, url: `https://www.youtube.com/watch?v=ctr${stamp}`, channel: 'Canal Alpha', channel_url: 'https://youtube.com/alpha' }],
  ['POST', '/channels', 'auth', { link: `https://www.youtube.com/channel/UCCTR${stamp}`, title: `Canal Contrato ${stamp}`, description: 'd', tags: ['t'], category: 'c' }],
  ['POST', '/devs', 'auth', { username: 'dev01' }],
  ['POST', '/video/refresh', 'auth', { record: [] }],
  ['POST', '/likes/devs/dev03', 'auth'], ['DELETE', '/likes/devs/dev03', 'auth'],
  ['POST', '/dislikes/devs/dev03', 'auth'], ['DELETE', '/dislikes/devs/dev03', 'auth'],
  ['POST', '/likes/channels/Canal%20Zeta', 'auth'], ['DELETE', '/likes/channels/Canal%20Zeta', 'auth'],
);

(async () => {
  let bad = 0;
  console.log(`# Contrato × ${BASE} — modo estrito${WRITES ? ' (com escritas)' : ' (somente GET)'}`);
  for (const [method, p, who, body] of probes) {
    const headers = {};
    if (who === 'auth') headers.Authorization = `Bearer ${TOKEN}`;
    if (body) headers['Content-Type'] = 'application/json';
    let res;
    try { res = await fetch(BASE + p, { method, headers, body: body ? JSON.stringify(body) : undefined, redirect: 'manual' }); }
    catch (e) { console.log(`ERRO  ${method} ${p}: ${e.message}`); bad++; continue; }
    const text = await res.text();
    const ct = res.headers.get('content-type') || '';
    const pathOnly = p.split('?')[0];
    const keys = Object.keys(spec.paths);
    // caminho literal tem precedência sobre o parametrizado (/video/refresh × /video/{id})
    const opPath = keys.find((k) => k === pathOnly) || keys.find((k) => new RegExp('^' + k.replace(/\{[^}]+\}/g, '[^/]+') + '$').test(pathOnly));
    const op = opPath && spec.paths[opPath][method.toLowerCase()];
    const def = resolve(op && op.responses && op.responses[String(res.status)]);
    const out = new Set();
    let note = '';
    if (!def) out.add(`status    ${res.status} não está entre as respostas do contrato (${op ? Object.keys(op.responses).join(',') : 'operação desconhecida'})`);
    else {
      const content = def.content && (def.content['application/json'] || def.content['text/html']);
      if (content && content.schema) {
        if (def.content['application/json']) {
          let parsed;
          try { parsed = JSON.parse(text); } catch (e) { out.add(`tipo      corpo não é JSON (content-type ${ct})`); }
          if (parsed !== undefined) check(content.schema, parsed, '$', out);
        } else {
          // contrato diz text/html + string
          if (!ct.includes('text/html')) out.add(`valor     content-type=${ct || '(vazio)'}, contrato=text/html`);
          else note = ' (contrato: text/html string)';
          if (ct.includes('application/json')) out.add(`valor     content-type=${ct}, contrato=text/html`);
        }
      } else if (!content && text.length > 0 && res.status < 300) note = ` (sem schema no contrato; corpo ${text.length} B)`;
    }
    console.log(`${out.size ? 'DIVERGE' : 'ok     '} ${res.status} ${method} ${p} [${who}]${note}`);
    for (const l of out) console.log('          ' + l);
    if (out.size) bad++;
  }
  console.log(`# ${probes.length} requisições, ${bad} com divergência`);
  process.exit(bad ? 1 : 0);
})();
