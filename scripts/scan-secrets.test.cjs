'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { scan } = require('./scan-secrets.cjs');

function repo(files) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'scan-'));
  for (const [name, content] of Object.entries(files)) {
    fs.mkdirSync(path.dirname(path.join(dir, name)), { recursive: true });
    fs.writeFileSync(path.join(dir, name), content);
  }
  return dir;
}
const bad = (files) => scan(repo(files));

test('arquivos limpos passam', () => assert.deepEqual(bad({ 'a.php': '<?php echo "oi";', '.env.example': 'JWT_SECRET=\nAPP_KEY=\n' }), []));
test('chave AWS é achada', () => assert.match(bad({ 'n.md': 'chave AKIAABCDEFGHIJKLMNOP' })[0], /chave de acesso da AWS/));
test('token do GitHub é achado', () => assert.match(bad({ 'n.md': `t ghp_${'a'.repeat(36)}` })[0], /token do GitHub/));
test('chave privada é achada', () => assert.match(bad({ 'k.pem': '-----BEGIN RSA PRIVATE KEY-----\nabc' })[0], /chave privada/));
test('JWT assinado é achado', () => assert.match(bad({ 't.txt': `Bearer eyJ${'a'.repeat(12)}.eyJ${'b'.repeat(12)}.${'c'.repeat(30)}` })[0], /JWT/));
test('URL de banco com senha é achada, a do docker local não', () => {
  assert.match(bad({ 'u.md': 'postgresql://neondb_owner:senhasecreta123@host/db' })[0], /URL de banco/);
  assert.deepEqual(bad({ 'u.md': 'postgresql://devfinder:devfinder@db:5432/devfinder' }), []);
});
test('master key do JSONBin (bcrypt) é achada', () => assert.match(bad({ 'j.md': `$2b$10$${'A'.repeat(50)}` })[0], /bcrypt/));
test('segredo atribuído a variável conhecida é achado; placeholder e bref-ssm não', () => {
  assert.match(bad({ 'c.md': `GITHUB_CLIENT_SECRET=${'a1'.repeat(20)}` })[0], /variável conhecida/);
  assert.deepEqual(bad({ 'c.md': 'GITHUB_CLIENT_SECRET=bref-ssm:/devfinder-laravel/prod/github-client-secret\nJWT_SECRET=<gere com openssl>' }), []);
});
test('.env versionado é reprovado, .env.example não', () => {
  assert.match(bad({ '.env': 'A=1' })[0], /\.env versionado/);
  assert.deepEqual(bad({ '.env.example': 'A=' }), []);
});
test('arquivo *.private.*, dump real e sufixo __ são reprovados', () => {
  assert.match(bad({ 'specs/acceptance/http-client.private.env.json': '{}' })[0], /private/);
  assert.match(bad({ 'specs/seed/omni8.devs.json': '[]' })[0], /dump real/);
  assert.match(bad({ 'scripts/import-real-seed__.php': '<?php' })[0], /sufixo __/);
});
test('a linha com o marcador scan-secrets:allow é ignorada (fixture falsa de propósito)', () => {
  assert.deepEqual(bad({ 'f.cjs': "const k = 'AKIAABCDEFGHIJKLMNOP'; // scan-secrets:allow" }), []);
});
