'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { check } = require('./allowlist.cjs');

const base = () => ({
  Resources: {
    Bucket: { Type: 'AWS::S3::Bucket' },
    Logs: { Type: 'AWS::Logs::LogGroup', Properties: { RetentionInDays: 7 } },
    Fn: { Type: 'AWS::Lambda::Function', Properties: { Environment: { Variables: { APP_KEY: 'bref-ssm:/x/app-key', APP_DEBUG: 'false', APP_ENV: 'production', CORS_ALLOWED_ORIGINS: 'https://app.example.test' } } } },
  },
});
const run = (mutate) => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'allow-'));
  const t = base();
  mutate(t);
  fs.writeFileSync(path.join(dir, 'cloudformation-template-update-stack.json'), JSON.stringify(t));
  return check(dir);
};

test('template permitido passa', () => assert.deepEqual(run(() => {}), []));
test('RDS é reprovado', () => assert.match(run((t) => { t.Resources.Db = { Type: 'AWS::RDS::DBInstance' }; })[0], /AWS::RDS::DBInstance/));
test('NAT Gateway é reprovado', () => assert.match(run((t) => { t.Resources.Nat = { Type: 'AWS::EC2::NatGateway' }; })[0], /NatGateway/));
test('API Gateway é reprovado', () => assert.match(run((t) => { t.Resources.Api = { Type: 'AWS::ApiGatewayV2::Api' }; })[0], /ApiGatewayV2/));
test('Secrets Manager é reprovado', () => assert.match(run((t) => { t.Resources.S = { Type: 'AWS::SecretsManager::Secret' }; })[0], /SecretsManager/));
test('log group sem retenção é reprovado', () => assert.match(run((t) => { delete t.Resources.Logs.Properties.RetentionInDays; })[0], /retenção/));
test('log group com retenção longa é reprovado', () => assert.match(run((t) => { t.Resources.Logs.Properties.RetentionInDays = 365; })[0], /retenção/));
test('APP_KEY em texto é reprovada', () => assert.match(run((t) => { t.Resources.Fn.Properties.Environment.Variables.APP_KEY = 'base64:abc'; })[0], /APP_KEY/));
test('URL de banco com senha em texto é reprovada', () => assert.match(run((t) => { t.Resources.Fn.Properties.Environment.Variables.DB_URL = 'postgres://u:senha@h/db'; })[0], /DB_URL/));
test('chave AWS em texto é reprovada', () => assert.match(run((t) => { t.Resources.Fn.Properties.Environment.Variables.X = 'AKIAABCDEFGHIJKLMNOP'; })[0], /chave de acesso/));
test('sem templates é reprovado', () => assert.match(check(fs.mkdtempSync(path.join(os.tmpdir(), 'allow-')))[0], /nenhum template/));
test('APP_DEBUG ligado é reprovado', () => assert.match(run((t) => { t.Resources.Fn.Properties.Environment.Variables.APP_DEBUG = 'true'; })[0], /APP_DEBUG/));
test('APP_ENV diferente de production é reprovado', () => assert.match(run((t) => { t.Resources.Fn.Properties.Environment.Variables.APP_ENV = 'local'; })[0], /APP_ENV/));
test('CORS com curinga é reprovado', () => assert.match(run((t) => { t.Resources.Fn.Properties.Environment.Variables.CORS_ALLOWED_ORIGINS = 'https://a.test, *'; })[0], /curinga/));
test('JWT_SECRET em texto é reprovado (Fase 4)', () => assert.match(run((t) => { t.Resources.Fn.Properties.Environment.Variables.JWT_SECRET = 'a'.repeat(40); })[0], /JWT_SECRET/));
test('GITHUB_CLIENT_SECRET em texto é reprovado (Fase 4)', () => assert.match(run((t) => { t.Resources.Fn.Properties.Environment.Variables.GITHUB_CLIENT_SECRET = 'abc123'; })[0], /GITHUB_CLIENT_SECRET/));
test('segredos da Fase 4 por bref-ssm: passam', () => assert.deepEqual(run((t) => {
  Object.assign(t.Resources.Fn.Properties.Environment.Variables, { JWT_SECRET: 'bref-ssm:/x/jwt-secret', GITHUB_CLIENT_SECRET: 'bref-ssm:/x/github-client-secret' });
}), []));
test('regra agendada do EventBridge passa (ingestão da Fase 6)', () => assert.deepEqual(run((t) => { t.Resources.Rule = { Type: 'AWS::Events::Rule', Properties: { ScheduleExpression: 'rate(12 hours)' } }; }), []));
test('regra do EventBridge sem agenda é reprovada', () => assert.match(run((t) => { t.Resources.Rule = { Type: 'AWS::Events::Rule', Properties: {} }; })[0], /sem ScheduleExpression/));
test('regra do EventBridge com padrão de evento é reprovada', () => assert.match(run((t) => { t.Resources.Rule = { Type: 'AWS::Events::Rule', Properties: { ScheduleExpression: 'rate(1 minute)', EventPattern: { source: ['aws.s3'] } } }; })[0], /EventPattern/));
test('JSONBIN_API_KEY em texto é reprovada (Fase 6)', () => assert.match(run((t) => { t.Resources.Fn.Properties.Environment.Variables.JSONBIN_API_KEY = '$2b$10$abcdefghijklmnopqrstuv'; })[0], /JSONBIN_API_KEY/));
