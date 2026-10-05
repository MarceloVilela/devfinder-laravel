<?php

declare(strict_types=1);

use App\Features\Auth\Support\TokenCodec;
use Database\Seeders\ParityDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

// Fase 7.1 / OWASP API8: o que vai para o log (mesmo canal JSON de produção, em arquivo) nunca carrega token, cookie, segredo nem URL de banco.

const LOG_FILE = 'devfinder-test-log.jsonl';

/** Aponta o canal `json` de produção para um arquivo e devolve o caminho. */
function captureLog(): string
{
    $path = sys_get_temp_dir() . '/' . LOG_FILE;
    @unlink($path);
    config(['logging.default' => 'json', 'logging.channels.json.handler_with' => ['stream' => $path], 'logging.channels.json.level' => 'debug']);
    Log::forgetChannel('json');

    return $path;
}

function logged(string $path): string
{
    return is_file($path) ? (string) file_get_contents($path) : '';
}

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

it('os caminhos de erro de autenticação logam, mas sem o token, o cookie nem o cabeçalho', function (): void {
    $path = captureLog();
    $valid = app(TokenCodec::class)->issue('dev01');
    $forged = $valid . 'x';

    $this->getJson('/v1/me')->assertStatus(401);
    $this->getJson('/v1/me', ['Authorization' => "Bearer {$forged}"])->assertStatus(401);
    $this->getJson('/v1/me', ['Authorization' => 'Basic ' . base64_encode('dev01:senha-do-dev01')])->assertStatus(401);
    $this->withUnencryptedCookie('devfinder_token', $forged)->withCredentials()->getJson('/v1/me')->assertStatus(401);

    $log = logged($path);
    expect($log)->not->toBe('')
        ->and($log)->not->toContain($valid)
        ->and($log)->not->toContain($forged)
        ->and($log)->not->toContain('senha-do-dev01')
        ->and($log)->not->toContain(base64_encode('dev01:senha-do-dev01'))
        ->and($log)->not->toMatch('/eyJ[A-Za-z0-9_-]{10,}\./')
        ->and($log)->not->toMatch('/authorization|cookie/i');
});

it('o login logado não carrega code, state, client_secret nem token do GitHub', function (): void {
    $path = captureLog();
    Http::fake([
        'github.com/login/oauth/access_token' => Http::response('boom', 500),
    ]);
    $start = $this->get('/v1/auth/github');
    parse_str((string) parse_url((string) $start->headers->get('Location'), PHP_URL_QUERY), $q);
    $state = is_string($q['state'] ?? null) ? $q['state'] : '';

    $this->withUnencryptedCookie('devfinder_oauth_state', $state)->get("/v1/auth/github/callback?code=codigo-secreto-do-github&state={$state}")->assertStatus(502);

    $log = logged($path);
    expect($log)->not->toContain('codigo-secreto-do-github')
        ->and($log)->not->toContain('test-client-secret')
        ->and($log)->not->toContain($state);
});

it('falha do JSONBin e do banco logam sem a chave nem a URL', function (): void {
    $path = captureLog();
    config(['devfinder.ingestion.jsonbin.api_key' => 'chave-secreta-do-bin', 'devfinder.ingestion.jsonbin.bin_id' => 'bin-secreto-123']);
    Http::fake(['api.jsonbin.io/*' => Http::response('boom', 503)]);

    $this->artisan('video:refresh')->assertExitCode(1);

    $log = logged($path);
    expect($log)->toContain('video:refresh: fonte indisponível')
        ->and($log)->not->toContain('chave-secreta-do-bin')
        ->and($log)->not->toMatch('/postgres(ql)?:\/\//');
});

it('uma exceção não tratada vai para o log sem argumentos de função (tokens) e sem a mensagem de banco na resposta', function (): void {
    $path = captureLog();
    config(['app.debug' => false]); // produção (o `ErrorRenderer` só devolve o 500 genérico com APP_DEBUG=false)
    $token = app(TokenCodec::class)->issue('dev01');
    app()->bind(App\Features\Dev\Queries\DevQueries::class, fn() => throw new RuntimeException('falha interna com senha=minha-senha-do-banco'));

    $response = $this->getJson('/v1/devs', ['Authorization' => "Bearer {$token}"])->assertStatus(500);

    expect($response->getContent())->not->toContain('minha-senha-do-banco');
    expect($response->getContent())->not->toContain('RuntimeException');
    expect(logged($path))->not->toContain($token);
});

it('o log de boot lista só os nomes das chaves, nunca os valores', function (): void {
    $names = app(App\Shared\Support\ConfigKeys::class)->present();

    expect(json_encode($names))->not->toContain('testing-secret');
    expect(json_encode($names))->not->toContain('test-client-secret');
});
