<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Studio\Gesso\HttpMethod;

uses(RefreshDatabase::class, Tests\Support\MatchesContract::class);

// Cada operação de escrita e relacionamento contra o OpenAPI (ADR 0007): sucesso e cada erro documentado.

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

it('POST /devs: 201, 404 e 422', function (): void {
    fakeGithubUsers(notFound: 'fantasma');
    $this->matchesContract($this->postJson('/v1/devs', ['username' => 'octocat'], as_dev('dev05')), HttpMethod::POST, '/devs', 201);
    $this->matchesContract($this->postJson('/v1/devs', ['username' => 'fantasma'], as_dev('dev05')), HttpMethod::POST, '/devs', 404);
    $this->matchesContract($this->postJson('/v1/devs', ['username' => '../x'], as_dev('dev05')), HttpMethod::POST, '/devs', 422);
});

it('POST /devs: 502 com o GitHub fora do ar', function (): void {
    Illuminate\Support\Facades\Http::fake(['api.github.com/*' => Illuminate\Support\Facades\Http::response('x', 500)]);
    $this->matchesContract($this->postJson('/v1/devs', ['username' => 'outro'], as_dev('dev05')), HttpMethod::POST, '/devs', 502);
});

it('POST /channels: 201, 200, 409 e 422', function (): void {
    $body = ['link' => 'https://x.test/c', 'title' => 'Canal Contrato', 'category' => 'c', 'tags' => ['a']];

    $this->matchesContract($this->postJson('/v1/channels', $body, as_admin('dev05')), HttpMethod::POST, '/channels', 201);
    $this->matchesContract($this->postJson('/v1/channels', $body, as_admin('dev05')), HttpMethod::POST, '/channels', 200);
    $this->matchesContract($this->postJson('/v1/channels', ['link' => 'https://youtube.com/beta'] + $body, as_admin('dev05')), HttpMethod::POST, '/channels', 409);
    $this->matchesContract($this->postJson('/v1/channels', [], as_admin('dev05')), HttpMethod::POST, '/channels', 422);
});

it('POST /channels e POST /video: 403 para quem não é ADMIN', function (): void {
    $this->matchesContract($this->postJson('/v1/channels', [], as_dev('dev05')), HttpMethod::POST, '/channels', 403);
    $this->matchesContract($this->postJson('/v1/video', [], as_dev('dev05')), HttpMethod::POST, '/video', 403);
});

it('POST /video: 201, 400, 409 e 422', function (): void {
    $video = ['title' => 't', 'url' => 'https://www.youtube.com/watch?v=contrato001', 'channel' => 'Canal Alpha', 'channel_url' => 'x'];

    $this->matchesContract($this->postJson('/v1/video', $video, as_admin('dev05')), HttpMethod::POST, '/video', 201);
    $this->matchesContract($this->postJson('/v1/video', $video, as_admin('dev05')), HttpMethod::POST, '/video', 409);
    $this->matchesContract($this->postJson('/v1/video', ['channel' => 'Nao Existe'] + $video, as_admin('dev05')), HttpMethod::POST, '/video', 400);
    $this->matchesContract($this->postJson('/v1/video', [], as_admin('dev05')), HttpMethod::POST, '/video', 422);
});

it('likes e dislikes de dev e de canal: 200 e 400, POST e DELETE', function (string $prefix, string $kind, string $target, string $missing): void {
    $template = "/{$prefix}/{$kind}/{username}";

    $this->matchesContract($this->postJson("/v1/{$prefix}/{$kind}/{$target}", [], as_dev('dev05')), HttpMethod::POST, $template, 200);
    $this->matchesContract($this->deleteJson("/v1/{$prefix}/{$kind}/{$target}", [], as_dev('dev05')), HttpMethod::DELETE, $template, 200);
    $this->matchesContract($this->postJson("/v1/{$prefix}/{$kind}/{$missing}", [], as_dev('dev05')), HttpMethod::POST, $template, 400);
    $this->matchesContract($this->deleteJson("/v1/{$prefix}/{$kind}/{$missing}", [], as_dev('dev05')), HttpMethod::DELETE, $template, 400);
})->with([
    ['likes', 'devs', 'dev04', 'nao-existe'],
    ['dislikes', 'devs', 'dev04', 'nao-existe'],
    ['likes', 'channels', 'Canal%20Zeta', 'nao-existe'],
    ['dislikes', 'channels', 'Canal%20Zeta', 'nao-existe'],
]);

it('GET /likes/devs, /dislikes/devs e /feed/subscriptions: 200', function (string $path, string $template): void {
    $this->postJson('/v1/likes/devs/dev04', [], as_dev('dev01'));

    $this->matchesContract($this->getJson("/v1{$path}", as_dev('dev01')), HttpMethod::GET, $template, 200);
})->with([['/likes/devs', '/likes/devs'], ['/dislikes/devs', '/dislikes/devs'], ['/feed/subscriptions', '/feed/subscriptions'], ['/feed/subscriptions?page=2', '/feed/subscriptions']]);

it('o 401 de toda operação autenticada cumpre o contrato', function (string $method, string $path, string $template): void {
    $response = $this->json($method, "/v1{$path}");

    $this->matchesContract($response, HttpMethod::from($method), $template, 401);
})->with([
    ['POST', '/devs', '/devs'], ['POST', '/channels', '/channels'], ['POST', '/video', '/video'],
    ['POST', '/likes/devs/dev04', '/likes/devs/{username}'], ['DELETE', '/dislikes/channels/x', '/dislikes/channels/{username}'],
    ['GET', '/likes/devs', '/likes/devs'], ['GET', '/feed/subscriptions', '/feed/subscriptions'],
]);

it('POST /auth/logout: 204 e o cookie de sessão limpo', function (): void {
    $this->matchesContract($this->postJson('/v1/auth/logout'), HttpMethod::POST, '/auth/logout', 204);
});

it('POST /video/refresh: 200, 401, 403 e 422', function (): void {
    $body = ['record' => [
        ['title' => 'Novo', 'url' => 'https://www.youtube.com/watch?v=REFRESHC001', 'channel' => 'Canal Alpha', 'channel_url' => 'x'],
        ['title' => 'Vídeo Alpha 01', 'url' => 'https://www.youtube.com/watch?v=vidalpha01', 'channel' => 'Canal Alpha', 'channel_url' => 'x'],
        ['title' => 'Sem canal', 'url' => 'https://www.youtube.com/watch?v=REFRESHC002', 'channel' => 'Nao Existe', 'channel_url' => 'y'],
    ]];

    $this->matchesContract($this->postJson('/v1/video/refresh', $body), HttpMethod::POST, '/video/refresh', 401);
    $this->matchesContract($this->postJson('/v1/video/refresh', $body, as_dev('dev06')), HttpMethod::POST, '/video/refresh', 403);
    $this->matchesContract($this->postJson('/v1/video/refresh', $body, as_admin('dev05')), HttpMethod::POST, '/video/refresh', 200);
    $this->matchesContract($this->postJson('/v1/video/refresh', ['record' => array_fill(0, 201, [])], as_admin('dev05')), HttpMethod::POST, '/video/refresh', 422);
});
