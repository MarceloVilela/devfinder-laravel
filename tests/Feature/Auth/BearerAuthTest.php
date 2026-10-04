<?php

declare(strict_types=1);

use App\Features\Auth\Support\TokenCodec;
use Database\Seeders\ParityDatasetSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

const SECRET = 'testing-secret-with-at-least-32-bytes-0123456789';

/** @param array<string, mixed> $claims */
function jwt(array $claims, string $secret = SECRET, string $alg = 'HS256'): string
{
    return JWT::encode($claims, $secret, $alg);
}

/** @return array<string, string> */
function bearer(string $token): array
{
    return ['Authorization' => "Bearer {$token}"];
}

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

it('GET /me devolve o Dev do token, com as reações', function (): void {
    $token = app(TokenCodec::class)->issue('dev01');

    $json = $this->getJson('/v1/me', bearer($token))->assertOk()->json();

    expect($json['user'])->toBe('dev01')
        ->and($json['likes'])->toHaveCount(1)
        ->and($json['deslikes'])->toHaveCount(1)
        ->and($json['follow'])->toHaveCount(1)
        ->and($json['ignore'])->toHaveCount(1);
});

it('cada token vê só o seu próprio perfil (de outro usuário)', function (): void {
    $codec = app(TokenCodec::class);

    expect($this->getJson('/v1/me', bearer($codec->issue('dev02')))->json('user'))->toBe('dev02');
    expect($this->getJson('/v1/me', bearer($codec->issue('dev03')))->json('user'))->toBe('dev03');
});

it('aceita o username sem caixa e o esquema bearer em qualquer caixa', function (): void {
    $this->getJson('/v1/me', ['Authorization' => 'bearer ' . app(TokenCodec::class)->issue('DEV01')])->assertOk()->assertJsonPath('user', 'dev01');
});

it('401 Token not provided sem o cabeçalho ou com ele vazio', function (?string $header): void {
    $headers = $header === null ? [] : ['Authorization' => $header];

    $this->getJson('/v1/me', $headers)->assertStatus(401)->assertExactJson(['error' => 'Token not provided.']);
})->with([null, '', '   ']);

it('401 Token invalid para todo token que não vale', function (string $case): void {
    $now = time();
    $valid = app(TokenCodec::class)->issue('dev01');
    [$h, $p, $s] = explode('.', $valid);
    $forged = rtrim(strtr(base64_encode((string) json_encode(['username' => 'dev02', 'iat' => $now, 'exp' => $now + 3600])), '+/', '-_'), '=');
    $none = rtrim(strtr(base64_encode((string) json_encode(['typ' => 'JWT', 'alg' => 'none'])), '+/', '-_'), '=');

    $header = match ($case) {
        'malformado' => 'Bearer garbage.invalid.token',
        'sem pontos' => 'Bearer abc',
        'payload trocado, assinatura antiga' => "Bearer {$h}.{$forged}.{$s}",
        'assinatura removida' => "Bearer {$h}.{$p}.",
        'alg none' => "Bearer {$none}.{$forged}.",
        'segredo errado' => 'Bearer ' . jwt(['username' => 'dev01', 'exp' => $now + 3600], 'outro-segredo-com-mais-de-32-bytes-aaaaaaaa'),
        'expirado' => 'Bearer ' . jwt(['username' => 'dev01', 'iat' => $now - 7200, 'exp' => $now - 1]),
        'sem exp' => 'Bearer ' . jwt(['username' => 'dev01']),
        'sem username' => 'Bearer ' . jwt(['sub' => 'dev01', 'exp' => $now + 3600]),
        'username que não é texto' => 'Bearer ' . jwt(['username' => ['dev01'], 'exp' => $now + 3600]),
        'username vazio' => 'Bearer ' . jwt(['username' => '', 'exp' => $now + 3600]),
        'ainda não válido (nbf)' => 'Bearer ' . jwt(['username' => 'dev01', 'nbf' => $now + 3600, 'exp' => $now + 7200]),
        'esquema Basic' => "Basic {$valid}",
        'sem esquema' => $valid,
        'dois tokens' => "Bearer {$valid} {$valid}",
        'dev inexistente' => 'Bearer ' . app(TokenCodec::class)->issue('fantasma'),
        default => throw new LogicException($case),
    };

    $this->getJson('/v1/me', ['Authorization' => $header])->assertStatus(401)->assertExactJson(['error' => 'Token invalid.']);
})->with([
    'malformado', 'sem pontos', 'payload trocado, assinatura antiga', 'assinatura removida', 'alg none', 'segredo errado', 'expirado',
    'sem exp', 'sem username', 'username que não é texto', 'username vazio', 'ainda não válido (nbf)', 'esquema Basic', 'sem esquema',
    'dois tokens', 'dev inexistente',
]);

it('401 para Dev apagado depois de emitir o token', function (): void {
    $token = app(TokenCodec::class)->issue('dev01');
    DB::table('devs')->where('username', 'dev01')->update(['deleted_at' => now()]);

    $this->getJson('/v1/me', bearer($token))->assertStatus(401)->assertJsonPath('error', 'Token invalid.');
});

it('nunca aceita o token na query string', function (): void {
    $token = app(TokenCodec::class)->issue('dev01');

    $this->getJson("/v1/me?token={$token}")->assertStatus(401)->assertJsonPath('error', 'Token not provided.');
    $this->getJson("/v1/me?access_token={$token}")->assertStatus(401);
});

it('o token de 7 dias expira em 604800 segundos', function (): void {
    [, $payload] = explode('.', app(TokenCodec::class)->issue('dev01'));
    /** @var array{exp: int, iat: int, username: string} $claims */
    $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

    expect($claims['exp'] - $claims['iat'])->toBe(604800)->and($claims['username'])->toBe('dev01');
});

it('recusa assinar e verificar com segredo curto', function (): void {
    $codec = new TokenCodec('curto', 60);

    expect(fn() => $codec->issue('dev01'))->toThrow(RuntimeException::class);
    expect(fn() => $codec->username('a.b.c'))->toThrow(RuntimeException::class);
});

it('rota opcional com token inválido, expirado ou de fantasma segue anônima, nunca 401 (D-2)', function (string $header): void {
    $this->getJson('/v1/devs', ['Authorization' => $header])->assertOk()->assertJsonPath('total', 35);
})->with([
    'Bearer garbage.invalid.token',
    'Basic xyz',
    'Bearer ',
    'lixo',
]);

it('rota opcional com token de fantasma ou expirado segue anônima', function (): void {
    $this->getJson('/v1/devs', bearer(app(TokenCodec::class)->issue('fantasma')))->assertOk()->assertJsonPath('total', 35);
    $this->getJson('/v1/devs', bearer(jwt(['username' => 'dev01', 'exp' => time() - 10])))->assertOk()->assertJsonPath('total', 35);
});
