'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { normalize, firstDifference } = require('./normalize-g3.cjs');

const UUID = (n) => `0198f0a1-0000-7000-8000-${String(n).padStart(12, '0')}`;

// Mesmo estado lógico, ids diferentes: o v1 (inteiros) e o php-laravel (UUIDv7).
const capturas = (id, extra = {}) => [
  {
    name: 'GET /devs', status: 200,
    body: {
      docs: [
        { _id: id(1), user: 'dev01', name: 'Dev 01', bio: 'b', avatar: 'a', likes: [id(2)], deslikes: [id(3)], follow: [id(10)], ignore: [id(11)], createdAt: 'x', updatedAt: 'y' },
        { _id: id(2), user: 'dev02', name: 'Dev 02', bio: 'b', avatar: 'a', likes: [], deslikes: [], follow: [], ignore: [], createdAt: 'x', updatedAt: 'y' },
        { _id: id(3), user: 'dev03', name: 'Dev 03', bio: null, avatar: 'a', likes: [], deslikes: [], follow: [], ignore: [], createdAt: 'x', updatedAt: 'y' },
      ],
      total: 3, itemsPerPage: 30, ...extra,
    },
  },
  {
    name: 'GET /channels', status: 200,
    body: [
      { _id: id(10), name: 'Canal Alpha', link: 'l', category: 'c', tags: ['javascript'], likes: [id(1)], deslikes: [], createdAt: 'x', updatedAt: 'y' },
      { _id: id(11), name: 'Canal Beta', link: 'l2', category: 'c', tags: [], likes: [], deslikes: [], createdAt: 'x', updatedAt: 'y' },
    ],
  },
  {
    name: 'GET /feed/trending', status: 200,
    body: { docs: [{ _id: id(100), title: 'Vídeo Alpha 01', url: 'https://www.youtube.com/watch?v=vidalpha01', channel_id: id(10), channel: 'Canal Alpha' }], total: 1, itemsPerPage: 30, ...extra },
  },
];

const v1 = capturas((n) => (n >= 100 ? n : n)); // inteiros
const laravel = capturas((n) => UUID(n), { page: 1, totalPages: 1 });

test('v1 (ids inteiros) e php-laravel (UUID + page/totalPages) ficam iguais depois de normalizar', () => {
  assert.equal(firstDifference(normalize(v1), normalize(laravel)), null);
});

test('ids viram a chave natural', () => {
  const [devs, , feed] = normalize(laravel);
  assert.equal(devs.body.docs[0]._id, 'dev01');
  assert.deepEqual(devs.body.docs[0].likes, ['dev02']);
  assert.deepEqual(devs.body.docs[0].follow, ['Canal Alpha']);
  assert.equal(feed.body.docs[0]._id, 'vidalpha01');
  assert.equal(feed.body.docs[0].channel_id, 'Canal Alpha');
});

test('page, totalPages, createdAt e updatedAt somem', () => {
  const [devs] = normalize(laravel);
  assert.equal('page' in devs.body, false);
  assert.equal('totalPages' in devs.body, false);
  assert.equal('createdAt' in devs.body.docs[0], false);
});

test('diferença real é detectada (ordem de lista é contrato)', () => {
  const trocado = structuredClone(laravel);
  trocado[0].body.docs.reverse();
  assert.notEqual(firstDifference(normalize(v1), normalize(trocado)), null);
});

test('diferença de valor é detectada', () => {
  const alterado = structuredClone(laravel);
  alterado[0].body.docs[0].name = 'Outro';
  assert.match(firstDifference(normalize(v1), normalize(alterado)), /name/);
});

test('ordem de chaves e de conjuntos de reação não importa', () => {
  const embaralhado = structuredClone(laravel);
  embaralhado[0].body.docs[0].likes = [UUID(3), UUID(2)];
  embaralhado[0].body.docs[0].deslikes = [UUID(3)];
  const base = structuredClone(laravel);
  base[0].body.docs[0].likes = [UUID(2), UUID(3)];
  base[0].body.docs[0].deslikes = [UUID(3)];
  assert.equal(firstDifference(normalize(base), normalize(embaralhado)), null);
});

test('id sem chave natural falha alto, não em silêncio', () => {
  const orfao = structuredClone(laravel);
  orfao[0].body.docs[0].follow = [UUID(999)];
  assert.throws(() => normalize(orfao), /sem chave natural/);
});
