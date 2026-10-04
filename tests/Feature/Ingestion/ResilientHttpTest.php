<?php

declare(strict_types=1);

use App\Features\Video\Exceptions\JsonBinUnavailable;
use App\Features\Video\Integrations\HttpJsonBinClient;
use App\Shared\Exceptions\GithubUnavailable;
use App\Shared\Github\HttpGithubClient;
use App\Shared\Http\ResilientHttp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

it('repete em 503 e 429 e devolve o sucesso, esperando o Retry-After e depois o backoff', function (): void {
    Http::fake(['x.test/*' => Http::sequence()->push('', 503, ['Retry-After' => '2'])->push('', 429)->push(['ok' => 1])]);

    $response = ResilientHttp::send(fn() => Http::get('https://x.test/a'));

    expect($response->status())->toBe(200);
    Sleep::assertSequence([Sleep::usleep(2_000_000), Sleep::usleep(1_000_000)]);
});

it('limita o Retry-After a 10 s e entende a data HTTP', function (): void {
    Http::fake(['x.test/*' => Http::sequence()->push('', 503, ['Retry-After' => '3600'])->push('', 503, ['Retry-After' => gmdate('D, d M Y H:i:s', time() + 4) . ' GMT'])->push(['ok' => 1])]);

    ResilientHttp::send(fn() => Http::get('https://x.test/a'));

    Sleep::assertSleptTimes(2);
    Sleep::assertSequence([Sleep::usleep(10_000_000), Sleep::usleep(4_000_000)]);
});

it('devolve a última resposta depois de 3 tentativas e não repete o que não é transitório', function (): void {
    Http::fake(['x.test/a' => Http::response('', 503), 'x.test/b' => Http::response('', 500), 'x.test/c' => Http::response('', 404)]);

    expect(ResilientHttp::send(fn() => Http::get('https://x.test/a'))->status())->toBe(503);
    Http::assertSentCount(3);
    Sleep::assertSleptTimes(2);

    expect(ResilientHttp::send(fn() => Http::get('https://x.test/b'))->status())->toBe(500);
    expect(ResilientHttp::send(fn() => Http::get('https://x.test/c'))->status())->toBe(404);
    Sleep::assertSleptTimes(2);
});

it('repete erro de conexão com backoff 0,5 s e 1 s e depois relança', function (): void {
    Http::fake(fn() => throw new ConnectionException('timeout'));

    expect(fn() => ResilientHttp::send(fn() => Http::get('https://x.test/a')))->toThrow(ConnectionException::class);

    Sleep::assertSequence([Sleep::usleep(500_000), Sleep::usleep(1_000_000)]);
});

it('o cliente do GitHub passa a repetir 503 e 429 (F6-8)', function (): void {
    $github = new HttpGithubClient('id', 'secret', null, 5);
    Http::fake(['api.github.com/*' => Http::sequence()->push('', 503)->push(['login' => 'octocat', 'name' => 'The Octocat', 'avatar_url' => 'https://a.test/o.png'])]);

    expect($github->publicProfile('octocat')?->login)->toBe('octocat');
    Sleep::assertSleptTimes(1);

});

it('o cliente do GitHub: 429 depois dos retries vira GithubUnavailable', function (): void {
    $github = new HttpGithubClient('id', 'secret', null, 5);
    Http::fake(['api.github.com/*' => Http::response(['message' => 'rate limit'], 429, ['Retry-After' => '1'])]);

    expect(fn() => $github->publicProfile('outro'))->toThrow(GithubUnavailable::class);
    Sleep::assertSleptTimes(2);
});

it('o cliente do JSONBin: lê a lista, manda a chave só no cabeçalho e valida o formato', function (): void {
    $bin = new HttpJsonBinClient('chave-do-bin', 'abc', 10);
    Http::fake(['api.jsonbin.io/*' => Http::response(['record' => [['title' => 'a']]])]);

    expect($bin->configured())->toBeTrue()->and($bin->candidates())->toBe([['title' => 'a']]);
    Http::assertSent(fn($r): bool => $r->hasHeader('X-Master-Key', 'chave-do-bin') && ! str_contains($r->url(), 'chave-do-bin'));

    expect((new HttpJsonBinClient('', '', 10))->configured())->toBeFalse();
    expect(fn() => (new HttpJsonBinClient('', '', 10))->candidates())->toThrow(JsonBinUnavailable::class);
});

it('o cliente do JSONBin: 5xx vira JsonBinUnavailable depois dos retries, sem vazar a chave', function (): void {
    $bin = new HttpJsonBinClient('chave-do-bin', 'abc', 10);
    Http::fake(['api.jsonbin.io/*' => Http::response('boom', 503)]);

    try {
        $bin->candidates();
        $message = '';
    } catch (JsonBinUnavailable $e) {
        $message = $e->getMessage();
    }

    expect($message)->toBe('JSONBin respondeu 503');
    expect($message)->not->toContain('chave-do-bin');
    Sleep::assertSleptTimes(2);
});

it('o cliente do JSONBin: 401 não é repetido', function (): void {
    $bin = new HttpJsonBinClient('chave-do-bin', 'abc', 10);
    Http::fake(['api.jsonbin.io/*' => Http::response('no', 401)]);

    expect(fn() => $bin->candidates())->toThrow(JsonBinUnavailable::class, 'JSONBin respondeu 401');
    Sleep::assertNeverSlept();
});

it('o cliente do JSONBin: erro de conexão vira JsonBinUnavailable sem repetir a mensagem de baixo nível', function (): void {
    $bin = new HttpJsonBinClient('chave-do-bin', 'abc', 10);
    Http::fake(fn() => throw new ConnectionException('timeout para chave-do-bin'));

    try {
        $bin->candidates();
        $message = '';
    } catch (JsonBinUnavailable $e) {
        $message = $e->getMessage();
    }

    expect($message)->toBe('JSONBin inacessível depois dos retries');
});
