<?php

declare(strict_types=1);

use App\Features\Auth\Support\TokenCodec;
use Database\Seeders\ParityDatasetSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

const SESSION = 'devfinder_token';

function tokenOf(string $username): string
{
    return app(TokenCodec::class)->issue($username);
}

it('o cookie de sessão autentica /me e as rotas autenticadas, sem Authorization (F4-13)', function (): void {
    $cookie = tokenOf('dev01');

    $this->withUnencryptedCookie(SESSION, $cookie)->withCredentials()->getJson('/v1/me')->assertOk()->assertJsonPath('user', 'dev01');
    $this->withUnencryptedCookie(SESSION, $cookie)->withCredentials()->getJson('/v1/likes/devs')->assertOk()->assertJsonCount(1);
    $this->withUnencryptedCookie(SESSION, $cookie)->withCredentials()->postJson('/v1/likes/devs/dev04')->assertOk()->assertJsonCount(2, 'likes');
    $this->withUnencryptedCookie(SESSION, $cookie)->withCredentials()->getJson('/v1/feed/subscriptions')->assertOk()->assertJsonPath('total', 20);
});

it('o cookie personaliza as rotas de autenticação opcional (é assim que o front pede /devs e /feed/trending)', function (): void {
    $cookie = tokenOf('dev01');

    // o cookie fica no cliente de teste depois de enviado: o anônimo vem primeiro
    $this->getJson('/v1/devs')->assertJsonPath('total', 35);
    $this->withUnencryptedCookie(SESSION, $cookie)->withCredentials()->getJson('/v1/devs')->assertJsonPath('total', 32);
    $this->withUnencryptedCookie(SESSION, $cookie)->withCredentials()->getJson('/v1/feed/trending')->assertJsonPath('total', 20);
});

it('o cookie vale antes do Authorization, como no original: cookie inválido não é salvo por um Bearer válido', function (): void {
    $this->withUnencryptedCookie(SESSION, 'lixo.invalido.token')->withCredentials()->getJson('/v1/me', as_dev('dev01'))
        ->assertStatus(401)->assertExactJson(['error' => 'Token invalid.']);

    $this->withUnencryptedCookie(SESSION, tokenOf('dev02'))->withCredentials()->getJson('/v1/me', as_dev('dev01'))->assertOk()->assertJsonPath('user', 'dev02');
});

it('o Bearer continua valendo sem cookie (Swagger UI e chamadas de servidor)', function (): void {
    $this->getJson('/v1/me', as_dev('dev01'))->assertOk()->assertJsonPath('user', 'dev01');
});

it('cookie de token inválido, expirado, de fantasma ou de dev apagado é 401 no /me e anônimo nas rotas opcionais', function (string $case): void {
    $now = time();
    $secret = 'testing-secret-with-at-least-32-bytes-0123456789';
    $value = match ($case) {
        'adulterado' => 'x' . tokenOf('dev01'),
        'expirado' => JWT::encode(['username' => 'dev01', 'iat' => $now - 7200, 'exp' => $now - 1], $secret, 'HS256'),
        'fantasma' => tokenOf('fantasma'),
        'apagado' => tokenOf('dev01'),
        default => throw new LogicException($case),
    };
    if ($case === 'apagado') {
        DB::table('devs')->where('username', 'dev01')->update(['deleted_at' => now()]);
    }

    $this->withUnencryptedCookie(SESSION, $value)->withCredentials()->getJson('/v1/me')->assertStatus(401)->assertExactJson(['error' => 'Token invalid.']);
    $this->withUnencryptedCookie(SESSION, $value)->withCredentials()->getJson('/v1/feed/trending')->assertOk()->assertJsonPath('total', 55);
})->with(['adulterado', 'expirado', 'fantasma', 'apagado']);

it('cookie vazio equivale a sem cookie: 401 Token not provided', function (): void {
    $this->withUnencryptedCookie(SESSION, '')->withCredentials()->getJson('/v1/me')->assertStatus(401)->assertExactJson(['error' => 'Token not provided.']);
});

it('o cookie do papel: promover e rebaixar vale também com a sessão por cookie', function (): void {
    $cookie = tokenOf('dev05');
    $body = ['link' => 'https://x.test/c', 'title' => 'Canal Cookie', 'category' => 'c'];

    $this->withUnencryptedCookie(SESSION, $cookie)->withCredentials()->postJson('/v1/channels', $body)->assertStatus(403);
    DB::table('devs')->where('username', 'dev05')->update(['role' => 'ADMIN']);
    $this->withUnencryptedCookie(SESSION, $cookie)->withCredentials()->postJson('/v1/channels', $body)->assertCreated();
});

it('POST /auth/logout limpa o cookie de sessão com os mesmos atributos e responde 204', function (): void {
    $response = $this->postJson('/v1/auth/logout')->assertNoContent();

    $cleared = current(array_filter($response->headers->getCookies(), static fn($c): bool => $c->getName() === SESSION)) ?: null;
    expect($cleared)->not->toBeNull();
    assert($cleared !== null);
    expect($cleared->getValue())->toBe('')
        ->and($cleared->getExpiresTime())->toBeLessThan(time())
        ->and($cleared->getPath())->toBe('/')
        ->and($cleared->isHttpOnly())->toBeTrue()
        ->and($cleared->getSameSite())->toBe('lax');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('logout é idempotente e funciona com ou sem sessão, mas só por POST', function (): void {
    $this->withUnencryptedCookie(SESSION, tokenOf('dev01'))->withCredentials()->postJson('/v1/auth/logout')->assertNoContent();
    $this->postJson('/v1/auth/logout')->assertNoContent();
    $this->getJson('/v1/auth/logout')->assertStatus(405);
});

it('o cookie nunca é aceito na query string nem em outro nome', function (): void {
    $token = tokenOf('dev01');

    $this->getJson("/v1/me?devfinder_token={$token}")->assertStatus(401);
    $this->withUnencryptedCookie('outro_nome', $token)->withCredentials()->getJson('/v1/me')->assertStatus(401);
});

it('a sessão por cookie não abre brecha de CSRF: SameSite=Lax no cookie e o CORS não libera credenciais nem origem desconhecida', function (): void {
    $this->get('/v1/devs', ['Origin' => 'https://evil.example.test'])->assertHeaderMissing('Access-Control-Allow-Credentials');
    $headers = $this->options('/v1/likes/devs/dev04', [], ['Origin' => 'https://evil.example.test', 'Access-Control-Request-Method' => 'POST'])->headers;
    expect($headers->get('Access-Control-Allow-Origin'))->not->toBe('https://evil.example.test');
    expect(config('devfinder.auth.session_cookie.same_site'))->toBe('lax');
});
