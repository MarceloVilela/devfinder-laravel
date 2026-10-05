#!/usr/bin/env node
// Varredura de segredos nos arquivos VERSIONADOS (Fase 7, OWASP API8): falha se achar chave, token ou senha. Roda no job `tools` do CI.
// Regra do projeto: segredo em nota ou arquivo versionado é incidente (chave AWS, token, cartão). Dado do dump real também não entra.
// Uso: node scripts/scan-secrets.cjs [diretório=.]   (usa `git ls-files`; fora de um repositório, varre os arquivos do diretório)
'use strict';
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const PATTERNS = [
  [/\b(AKIA|ASIA)[0-9A-Z]{16}\b/, 'chave de acesso da AWS'],
  [/\bgh[pousr]_[A-Za-z0-9]{36,}\b/, 'token do GitHub'],
  [/\bgithub_pat_[A-Za-z0-9_]{50,}\b/, 'token do GitHub (fine-grained)'],
  [/-----BEGIN [A-Z ]*PRIVATE KEY-----/, 'chave privada'],
  [/\beyJ[A-Za-z0-9_-]{10,}\.eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{20,}\b/, 'JWT completo (assinado)'],
  [/\bpostgres(ql)?:\/\/(?!devfinder:devfinder@)[^\s:@/]+:[^\s@/]{4,}@/, 'URL de banco com senha'],
  [/\$2[aby]\$\d{2}\$[A-Za-z0-9./]{40,}/, 'chave bcrypt (ex.: master key do JSONBin)'],
  [/\bxox[baprs]-[A-Za-z0-9-]{20,}\b/, 'token do Slack'],
  [/\bAIza[0-9A-Za-z_-]{35}\b/, 'chave de API do Google'],
  [/\bsk-[A-Za-z0-9]{32,}\b/, 'chave de API (sk-)'],
  [/\b(client_secret|github_client_secret|jwt_secret|app_key)\s*[=:]\s*['"]?(?!<|\$\{|bref-ssm:|test|local|testing|base64:AAAA)[A-Za-z0-9+/_=-]{32,}['"]?/i, 'segredo atribuído a variável conhecida'],
];

// Arquivos que nunca devem estar no repositório, mesmo vazios de padrão.
const FORBIDDEN_PATHS = [
  [/(^|\/)\.env(\.[^/]*)?$/, 'arquivo .env versionado (só .env.example)', /(^|\/)\.env\.example$/],
  [/\.private\./, 'arquivo *.private.* versionado'],
  [/(^|\/)specs\/seed\/[^/]*\.json$/, 'dump real em specs/seed (marca e LGPD)'],
  [/__\.[^/]*$/, 'arquivo local com sufixo __ versionado'],
  [/(^|\/)omni8\./, 'dump real (omni8.*) versionado'],
];

// Fixtures de teste com segredo FALSO de propósito: marcador na própria linha, ou o arquivo de testes do próprio scanner.
const ALLOW_MARKER = 'scan-secrets:allow';
const SKIP_FILES = new Set(['scripts/scan-secrets.test.cjs']);

function files(root) {
  try {
    return execFileSync('git', ['-C', root, 'ls-files', '-z'], { encoding: 'utf8' }).split('\0').filter(Boolean);
  } catch {
    const out = [];
    const walk = (dir) => {
      for (const e of fs.readdirSync(path.join(root, dir), { withFileTypes: true })) {
        if (['.git', 'node_modules', 'vendor'].includes(e.name)) continue;
        const rel = path.join(dir, e.name);
        e.isDirectory() ? walk(rel) : out.push(rel);
      }
    };
    walk('');
    return out;
  }
}

function scan(root) {
  const problems = [];
  for (const rel of files(root)) {
    if (SKIP_FILES.has(rel)) continue;
    for (const [re, why, except] of FORBIDDEN_PATHS) {
      if (re.test(rel) && !(except && except.test(rel))) problems.push(`${rel}: ${why}`);
    }
    const full = path.join(root, rel);
    let stat;
    try { stat = fs.statSync(full); } catch { continue; }
    if (!stat.isFile() || stat.size > 2_000_000) continue;
    const text = fs.readFileSync(full, 'latin1');
    if (text.includes('\0')) continue; // binário
    text.split('\n').forEach((line, i) => {
      if (line.includes(ALLOW_MARKER)) return;
      for (const [re, why] of PATTERNS) {
        if (re.test(line)) problems.push(`${rel}:${i + 1}: ${why}`);
      }
    });
  }
  return problems;
}

module.exports = { scan };

if (require.main === module) {
  const root = process.argv[2] || '.';
  const problems = scan(root);
  if (problems.length) {
    console.error(problems.map((p) => `SEGREDO? ${p}`).join('\n'));
    process.exit(1);
  }
  console.log(`varredura de segredos OK (${files(root).length} arquivos)`);
}
