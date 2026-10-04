#!/usr/bin/env node
// Allowlist de recursos (ADR 0010, regra de custo): lê os templates do CloudFormation gerados por `osls package`
// e FALHA se houver tipo de recurso fora da lista, segredo em variável de ambiente ou log group sem retenção curta.
// Uso: node scripts/allowlist.cjs [.serverless]      (sem dependências)
'use strict';
const fs = require('fs');
const path = require('path');

// Cada tipo novo exige decisão registrada (ADR) antes de entrar aqui. Fase 6 acrescentará o agendador.
const ALLOWED = new Set([
  'AWS::S3::Bucket',        // bucket de artefatos do próprio osls (não é bucket da aplicação)
  'AWS::S3::BucketPolicy',
  'AWS::Logs::LogGroup',
  'AWS::IAM::Role',
  'AWS::Lambda::Function',
  'AWS::Lambda::Version',
  'AWS::Lambda::Url',
  'AWS::Lambda::Permission',
]);
const MAX_RETENTION_DAYS = 14;
const SECRET_PATTERNS = [
  [/^base64:/i, 'chave de aplicação em texto (use bref-ssm:)'],
  [/:\/\/[^/\s:@]+:[^@\s]+@/, 'URL com usuário e senha em texto (use bref-ssm:)'],
  [/AKIA[0-9A-Z]{12,}/, 'chave de acesso da AWS'],
  [/-----BEGIN [A-Z ]*PRIVATE KEY-----/, 'chave privada'],
];

function check(dir) {
  const problems = [];
  const files = fs.readdirSync(dir).filter((f) => /^cloudformation-template-.*\.json$/.test(f));
  if (files.length === 0) problems.push(`nenhum template em ${dir} (rode scripts/package.sh)`);
  for (const f of files) {
    const t = JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8'));
    for (const [id, r] of Object.entries(t.Resources || {})) {
      if (!ALLOWED.has(r.Type)) problems.push(`${f}: recurso fora da allowlist: ${r.Type} (${id})`);
      if (r.Type === 'AWS::Logs::LogGroup') {
        const days = r.Properties && r.Properties.RetentionInDays;
        if (!(Number.isInteger(days) && days <= MAX_RETENTION_DAYS)) problems.push(`${f}: log group ${id} sem retenção de até ${MAX_RETENTION_DAYS} dias`);
      }
      if (r.Type === 'AWS::Lambda::Function') {
        const vars = (r.Properties.Environment && r.Properties.Environment.Variables) || {};
        if (vars.APP_DEBUG !== 'false') problems.push(`${f}: APP_DEBUG deve ser 'false' (veio ${JSON.stringify(vars.APP_DEBUG)})`);
        if (vars.APP_ENV !== 'production') problems.push(`${f}: APP_ENV deve ser 'production' (veio ${JSON.stringify(vars.APP_ENV)})`);
        if (/^\*$/.test(String(vars.CORS_ALLOWED_ORIGINS || '').trim()) || String(vars.CORS_ALLOWED_ORIGINS || '').split(',').some((o) => o.trim() === '*')) problems.push(`${f}: CORS_ALLOWED_ORIGINS com curinga (D-4)`);
        // Segredos só por referência ao SSM (resolvida pelo runtime do Bref), nunca em texto na configuração da função.
        for (const k of ['APP_KEY', 'DB_URL', 'JWT_SECRET', 'GITHUB_CLIENT_SECRET']) {
          if (vars[k] !== undefined && !String(vars[k]).startsWith('bref-ssm:')) problems.push(`${f}: ${k} deve ser bref-ssm:, não texto`);
        }
        for (const [k, v] of Object.entries(vars)) {
          if (typeof v !== 'string') continue;
          for (const [re, why] of SECRET_PATTERNS) if (re.test(v)) problems.push(`${f}: variável ${k} com ${why}`);
        }
      }
    }
  }
  return problems;
}

module.exports = { check, ALLOWED };

if (require.main === module) {
  const dir = process.argv[2] || '.serverless';
  const problems = check(dir);
  if (problems.length) { console.error(problems.map((p) => `FALHA: ${p}`).join('\n')); process.exit(1); }
  console.log(`allowlist OK (${[...ALLOWED].length} tipos permitidos)`);
}
