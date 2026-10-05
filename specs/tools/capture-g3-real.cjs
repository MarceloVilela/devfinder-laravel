#!/usr/bin/env node
// G3 com DADOS REAIS (Fase 7): captura as rotas públicas de leitura de UM lado e grava o JSON que o `normalize-g3.cjs` compara.
// Os casos NÃO ficam escritos aqui (são dados de terceiros, marca e LGPD): saem da própria API na hora, por posição nas listagens
// (primeiro, do meio e último de cada uma) e por termos tirados dos nomes e títulos. Dois lados com os mesmos dados escolhem os mesmos casos.
// Uso: node capture-g3-real.cjs <baseUrl com /v1> <saida.json>   (saída fora do repositório: contém nomes reais)
// Só GET, sem token: não altera nada no servidor. Se o servidor for o deploy real, a carga é de ~60 requisições leves.
'use strict';
const fs = require('fs');

const [base, out] = process.argv.slice(2);
if (!base || !out) { console.error('uso: capture-g3-real.cjs <baseUrl> <saida.json>'); process.exit(2); }

const get = async (path) => {
  const res = await fetch(base + path, { headers: { Accept: '*/*' } });
  const text = await res.text();
  const type = res.headers.get('content-type') ?? '';
  return { name: `GET ${path}`, status: res.status, contentType: type.split(';')[0], body: type.includes('json') ? JSON.parse(text) : text };
};
const pick = (list, ...idx) => idx.map((i) => list[Math.min(Math.max(i < 0 ? list.length + i : i, 0), list.length - 1)]).filter(Boolean);
const uniq = (a) => [...new Set(a)];
const word = (s) => (s.normalize('NFD').replace(/[^\p{L}\p{N} ]/gu, '').split(' ').find((w) => w.length >= 4) ?? '');

(async () => {
  const captures = [];
  const add = async (path) => { const c = await get(path); captures.push(c); return c; };

  await add('/');
  const devs1 = await add('/devs?page=1');
  await add('/devs?page=2'); await add('/devs?page=999'); await add('/devs?page=abc');
  const users = uniq([...pick(devs1.body.docs.map((d) => d.user), 0, 10, 20, -1), 'marcelovilela']);
  for (const u of users) await add(`/devs/${encodeURIComponent(u)}`);
  await add('/devs/nao-existe-xyz');

  const channels = await add('/channels');
  const names = channels.body.map((c) => c.name);
  const accented = names.find((n) => /[^\x00-\x7F]/.test(n));
  // Nome com acento no segmento da URL: o v1 responde 400 (CodeIgniter recusa caractere não ASCII em `permittedURIChars`, mesmo codificado); o php-laravel
  // responde 200. É defeito do v1: fica fora do diff, salvo com G3_ACCENTED=1.
  const sample = uniq([...pick(names, 0, 40, 80, 120, -1), ...(accented && process.env.G3_ACCENTED ? [accented] : [])]);
  for (const n of sample) await add(`/channels/${encodeURIComponent(n)}`);
  await add('/channels/nao-existe-xyz');

  // Vídeos: o `created_at` do v1 é DATETIME (segundos) e 429 dos 500 vídeos do dump dividem o segundo com outro; a ordem dentro do segundo é indefinida no v1
  // (o php-laravel guarda os milissegundos e ordena de verdade). Por isso, em vez de comparar página a página, compara-se o CONJUNTO de vídeos de todas as
  // páginas, o total, o tamanho de cada página e o clamp de página além do fim. A ordem do php-laravel é conferida pelos testes.
  const idOf = (v) => /[?&]v=([^&]+)/.exec(v.url)?.[1];
  const listing = async (path, label) => {
    const first = await get(`${path}${path.includes('?') ? '&' : '?'}page=1`);
    const pages = Math.ceil(first.body.total / first.body.itemsPerPage);
    const sizes = [first.body.docs.length];
    const docs = [...first.body.docs];
    for (let p = 2; p <= pages; p++) { const r = await get(`${path}${path.includes('?') ? '&' : '?'}page=${p}`); sizes.push(r.body.docs.length); docs.push(...r.body.docs); }
    const beyond = await get(`${path}${path.includes('?') ? '&' : '?'}page=9999`);
    // Entre dois php-laravel (mesma ordenação) a ordem página a página também conta: G3_VIDEO_ORDER=1 grava as páginas cruas.
    if (process.env.G3_VIDEO_ORDER) for (let p = 1; p <= pages; p++) captures.push(await get(`${path}${path.includes('?') ? '&' : '?'}page=${p}`));
    captures.push({ name: `GET ${label} (todas as páginas, como conjunto)`, status: first.status, contentType: first.contentType, body: { total: first.body.total, itemsPerPage: first.body.itemsPerPage, pageSizes: sizes, beyondEndSize: beyond.body.docs.length, videos: docs.map(idOf).sort() } });
    return docs;
  };
  const trending = await listing('/feed/trending', '/feed/trending');
  for (const n of pick(names, 0, 60, 120)) await listing(`/feed/channel?channel_name=${encodeURIComponent(n)}`, `/feed/channel?channel_name=<canal ${names.indexOf(n)}>`);
  await add('/feed/channel?channel_name=NaoExiste');

  const ids = trending.map(idOf).filter(Boolean).sort();
  for (const id of pick(ids, 0, 100, 250, -1)) await add(`/video/${encodeURIComponent(id)}`);
  await add('/video/nao-existe-xyz');

  const titles = trending.map((v) => v.title).sort();
  const terms = uniq([...pick(names, 0, 30, 60).map(word), ...pick(titles, 0, 100, 250).map(word), 'a', '%', 'ç']).filter(Boolean);
  // O v1 não tem `GET /search` (404, A1/D-5): só entra no diff entre dois php-laravel (deploy × local), com G3_SEARCH=1.
  if (process.env.G3_SEARCH) for (const t of terms) await add(`/search?q=${encodeURIComponent(t)}`);

  // `/description/feed` lista os canais dos 30 vídeos mais recentes: depende da ordem dentro do segundo (indefinida no v1). Só com G3_VIDEO_ORDER=1
  // (entre dois php-laravel, que ordenam igual).
  if (process.env.G3_VIDEO_ORDER) await add('/description/feed');
  // `/description/category`: o texto traz as hashtags das tags; o `tags.name` do v1 é `_ai_ci` e une `C` e `c` (ou `segurança` e `seguranca`), o php-laravel
  // guarda cada grafia (divergência D-18). Compara-se a estrutura: categorias em ordem, canais em ordem e hashtags como conjunto sem caixa e sem acento.
  const cat = await get('/description/category');
  const fold = (t) => t.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
  const blocks = String(cat.body).split('\n').filter(Boolean).map((b) => {
    const m = /^Encontre canais sobre (.*?) em (\S+) <br \/><br \/>(.*?)<br \/><br \/>Repositório da aplicação web: (.*?) <br \/><br \/>Meu github: (.*?) <br \/><br \/>(.*?)<br \/><br \/>-{20}<br \/><br \/>$/s.exec(b);
    return m ? { category: m[1], link: m[2], names: m[3].split('<br />'), repo: m[4], github: m[5], hashtags: [...new Set(m[6].split('<br />').filter(Boolean).map(fold))].sort() } : { raw: b.slice(0, 80) };
  });
  captures.push({ name: 'GET /description/category (estrutura)', status: cat.status, contentType: cat.contentType, body: blocks });

  fs.writeFileSync(out, `${JSON.stringify(captures, null, 2)}\n`);
  console.log(`${captures.length} capturas em ${out}`);
})().catch((e) => { console.error(e); process.exit(1); });
