<?php

declare(strict_types=1);

use App\Features\Auth\Support\TokenCodec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

uses(RefreshDatabase::class);

const STATE_COOKIE = 'devfinder_oauth_state';

/**
 * Passo 1 do fluxo: devolve a resposta, o `state` da URL e o valor do cookie (devem ser iguais).
 *
 * @return array{0: TestResponse<Symfony\Component\HttpFoundation\Response>, 1: string, 2: string}
 */
function startLogin(TestCase $test): array
{
    $response = $test->get('/v1/auth/github')->assertRedirect();
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);
    $state = $query['state'] ?? '';

    return [$response, is_string($state) ? $state : '', (string) $response->getCookie(STATE_COOKIE, false)?->getValue()];
}

/** @param array<string, mixed> $profile */
function fakeGithub(array $profile = []): void
{
    Http::fake([
        'github.com/login/oauth/access_token' => Http::response(['access_token' => 'gho_fake']),
        'api.github.com/user' => Http::response($profile + [
            'login' => 'Octo-Cat', 'name' => 'Octo Cat', 'bio' => null, 'avatar_url' => 'https://avatars.example.test/octo.png',
        ]),
    ]);
}

/** @return TestResponse<Symfony\Component\HttpFoundation\Response> */
function callback(TestCase $test, string $query, ?string $cookie): TestResponse
{
    if ($cookie !== null) {
        $test->withUnencryptedCookie(STATE_COOKIE, $cookie);
    }

    return $test->get("/v1/auth/github/callback?{$query}");
}

it('redireciona ao GitHub com client_id e state, sem scope, e grava o cookie do state', function (): void {
    [$response, $state, $cookie] = startLogin($this);
    $location = (string) $response->headers->get('Location');

    expect($location)->toStartWith('https://github.com/login/oauth/authorize?')
        ->and($location)->toContain('client_id=test-client-id')
        ->and($location)->not->toContain('scope=')
        ->and($location)->not->toContain('redirect_uri=')
        ->and($state)->toHaveLength(64)
        ->and($cookie)->toBe($state);

    $setCookie = (string) $response->headers->get('Set-Cookie');
    expect(strtolower($setCookie))->toContain('httponly')->toContain('samesite=lax')->toContain('path=/v1/auth');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('manda redirect_uri quando configurada', function (): void {
    config(['devfinder.auth.github.redirect_uri' => 'https://api.example.test/v1/auth/github/callback']);

    $location = (string) $this->get('/v1/auth/github')->headers->get('Location');

    expect($location)->toContain('redirect_uri=' . urlencode('https://api.example.test/v1/auth/github/callback'));
});

it('gera um state diferente a cada login', function (): void {
    expect(startLogin($this)[1])->not->toBe(startLogin($this)[1]);
});

/** Valor do cookie de sessão emitido na resposta, ou null. */
/** @param TestResponse<Symfony\Component\HttpFoundation\Response> $response */
function sessionCookieOf(TestResponse $response): ?Symfony\Component\HttpFoundation\Cookie
{
    foreach ($response->headers->getCookies() as $cookie) {
        if ($cookie->getName() === 'devfinder_token' && $cookie->getValue() !== '') {
            return $cookie;
        }
    }

    return null;
}

it('conclui o login: cria o Dev em minúsculas e abre a sessão por cookie httpOnly, sem token na URL (F4-13)', function (): void {
    fakeGithub();
    [, $state, $cookie] = startLogin($this);

    $response = callback($this, "code=abc&state={$state}", $cookie)->assertRedirect('https://app.example.test/login');

    $session = sessionCookieOf($response);
    expect($session)->not->toBeNull();
    assert($session !== null);
    expect(app(TokenCodec::class)->username((string) $session->getValue()))->toBe('octo-cat')
        ->and($session->isHttpOnly())->toBeTrue()
        ->and($session->getSameSite())->toBe('lax')
        ->and($session->getPath())->toBe('/')
        ->and($session->getExpiresTime())->toBeGreaterThan(time() + 604000)->toBeLessThanOrEqual(time() + 604800)
        ->and((string) $response->headers->get('Location'))->not->toContain('token');
    expect($response->headers->get('Referrer-Policy'))->toBe('no-referrer');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    $dev = DB::table('devs')->where('username', 'octo-cat')->first();
    assert($dev !== null);
    expect($dev->name)->toBe('Octo Cat')
        ->and($dev->bio)->toBe('')
        ->and($dev->avatar)->toBe('https://avatars.example.test/octo.png');
});

it('a sessão aberta no callback vale no /me, só com o cookie', function (): void {
    fakeGithub();
    [, $state, $cookie] = startLogin($this);
    $session = sessionCookieOf(callback($this, "code=abc&state={$state}", $cookie));
    assert($session !== null);

    $this->withUnencryptedCookie('devfinder_token', $session->getValue())->withCredentials()->getJson('/v1/me')->assertOk()->assertJsonPath('user', 'octo-cat');
});

it('Secure só em produção', function (): void {
    config(['devfinder.auth.session_cookie.secure' => true]);
    fakeGithub();
    [, $state, $cookie] = startLogin($this);

    $session = sessionCookieOf(callback($this, "code=abc&state={$state}", $cookie));

    expect($session?->isSecure())->toBeTrue();
});

it('apaga o cookie do state no callback', function (): void {
    fakeGithub();
    [, $state, $cookie] = startLogin($this);

    $response = callback($this, "code=abc&state={$state}", $cookie);

    $cleared = array_filter($response->headers->getCookies(), static fn($c): bool => $c->getName() === STATE_COOKIE && $c->getValue() === '');
    expect($cleared)->toHaveCount(1);
});

it('reaproveita o Dev existente, sem duplicar, mesmo com outra caixa no login', function (): void {
    fakeGithub();
    foreach ([1, 2] as $_) {
        [, $state, $cookie] = startLogin($this);
        expect(sessionCookieOf(callback($this, "code=abc&state={$state}", $cookie)))->not->toBeNull();
    }

    expect(DB::table('devs')->whereRaw("norm_text(username) = 'octo-cat'")->count())->toBe(1);
});

it('manda ao login do front, sem token, quando falta o code, o state ou o cookie, ou eles não casam', function (string $case): void {
    fakeGithub();
    [, $state, $cookie] = startLogin($this);

    $response = match ($case) {
        'sem code' => callback($this, "state={$state}", $cookie),
        'code vazio' => callback($this, "code=&state={$state}", $cookie),
        'sem state' => callback($this, 'code=abc', $cookie),
        'sem cookie' => callback($this, "code=abc&state={$state}", null),
        'state diferente' => callback($this, 'code=abc&state=' . str_repeat('a', 64), $cookie),
        'cookie diferente' => callback($this, "code=abc&state={$state}", str_repeat('b', 64)),
        'usuário negou' => callback($this, "error=access_denied&state={$state}", $cookie),
        default => throw new LogicException($case),
    };

    $response->assertRedirect('https://app.example.test/login');
    expect(sessionCookieOf($response))->toBeNull();
    Http::assertNothingSent();
    expect(DB::table('devs')->count())->toBe(0);
})->with(['sem code', 'code vazio', 'sem state', 'sem cookie', 'state diferente', 'cookie diferente', 'usuário negou']);

it('não aceita o mesmo state depois que o cookie foi apagado (repetição)', function (): void {
    fakeGithub();
    [, $state] = startLogin($this);

    callback($this, "code=abc&state={$state}", null)->assertRedirect('https://app.example.test/login');
});

it('manda ao login do front quando o GitHub recusa o code', function (): void {
    Http::fake(['github.com/login/oauth/access_token' => Http::response(['error' => 'bad_verification_code'])]);
    [, $state, $cookie] = startLogin($this);

    callback($this, "code=velho&state={$state}", $cookie)->assertRedirect('https://app.example.test/login');
    expect(DB::table('devs')->count())->toBe(0);
});

it('responde 502 sem vazar detalhe quando o GitHub está indisponível', function (string $case): void {
    match ($case) {
        'troca 500' => Http::fake(['github.com/login/oauth/access_token' => Http::response('boom', 500)]),
        'troca sem token nem erro' => Http::fake(['github.com/login/oauth/access_token' => Http::response(['x' => 1])]),
        'perfil 500' => Http::fake([
            'github.com/login/oauth/access_token' => Http::response(['access_token' => 'gho_x']),
            'api.github.com/user' => Http::response('boom', 500),
        ]),
        'perfil 401' => Http::fake([
            'github.com/login/oauth/access_token' => Http::response(['access_token' => 'gho_x']),
            'api.github.com/user' => Http::response(['message' => 'Bad credentials'], 401),
        ]),
        'perfil sem login' => Http::fake([
            'github.com/login/oauth/access_token' => Http::response(['access_token' => 'gho_x']),
            'api.github.com/user' => Http::response(['name' => 'sem login']),
        ]),
        'falha de rede' => Http::fake(fn() => throw new Illuminate\Http\Client\ConnectionException('timeout')),
        default => throw new LogicException($case),
    };
    [, $state, $cookie] = startLogin($this);

    $response = callback($this, "code=abc&state={$state}", $cookie);

    $response->assertStatus(502)->assertExactJson(['error' => 'GitHub unavailable.']);
    expect($response->getContent())->not->toContain('boom');
    expect($response->getContent())->not->toContain('timeout');
    expect(DB::table('devs')->count())->toBe(0);
})->with(['troca 500', 'troca sem token nem erro', 'perfil 500', 'perfil 401', 'perfil sem login', 'falha de rede']);

it('envia o client_secret só ao GitHub, com timeout, e nunca o devolve', function (): void {
    fakeGithub();
    [, $state, $cookie] = startLogin($this);

    $response = callback($this, "code=abc&state={$state}", $cookie);

    Http::assertSent(fn($request): bool => $request->url() === 'https://github.com/login/oauth/access_token'
        && $request['client_secret'] === 'test-client-secret' && $request['code'] === 'abc');
    expect($response->getContent())->not->toContain('test-client-secret');
});

it('limita o login: o 11º pedido por minuto recebe 429 com Retry-After (F4-10)', function (): void {
    foreach (range(1, 10) as $_) {
        $this->get('/v1/auth/github')->assertRedirect();
    }

    $this->get('/v1/auth/github')->assertStatus(429)->assertHeader('Retry-After')->assertExactJson(['error' => 'Too many requests.']);
    $this->get('/v1/auth/github/callback')->assertStatus(429);
});

it('não limita /me nem as leituras', function (): void {
    foreach (range(1, 12) as $_) {
        $this->getJson('/v1/devs')->assertOk();
        $this->getJson('/v1/me')->assertStatus(401);
    }
});
