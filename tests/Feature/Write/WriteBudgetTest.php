<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

// Orçamento da Fase 1, revisto na spec da Fase 5 (`fase-5-escrita.md`): independe do tamanho dos dados e inclui o `auth`.
it('cada operação respeita o orçamento de queries', function (string $method, string $path, array $body, int $budget): void {
    fakeGithubUsers();
    $headers = as_admin('dev05');

    $queries = countQueries(fn() => $this->json($method, $path, $body, $headers));

    expect($queries)->toBeLessThanOrEqual($budget);
})->with([
    'POST /devs novo' => ['POST', '/v1/devs', ['username' => 'orcamento1'], 6],
    'POST /devs existente' => ['POST', '/v1/devs', ['username' => 'dev01'], 6],
    'POST /channels novo' => ['POST', '/v1/channels', ['link' => 'https://x.test/1', 'title' => 'Orc 1', 'category' => 'c', 'tags' => ['a', 'b', 'c']], 8],
    'POST /channels novo com userGithub' => ['POST', '/v1/channels', ['link' => 'https://x.test/2', 'title' => 'Orc 2', 'category' => 'c', 'tags' => ['a'], 'userGithub' => 'orcgh'], 10],
    'POST /channels existente' => ['POST', '/v1/channels', ['link' => 'https://youtube.com/alpha', 'title' => 'Canal Alpha', 'category' => 'c', 'tags' => ['a', 'b']], 9],
    'POST /video' => ['POST', '/v1/video', ['title' => 't', 'url' => 'https://www.youtube.com/watch?v=orc00000001', 'channel' => 'Canal Alpha', 'channel_url' => 'x'], 5],
    'POST /video 409' => ['POST', '/v1/video', ['title' => 't', 'url' => 'https://www.youtube.com/watch?v=vidalpha01', 'channel' => 'Canal Alpha', 'channel_url' => 'x'], 6],
    'POST likes/devs' => ['POST', '/v1/likes/devs/dev04', [], 5],
    'DELETE likes/devs' => ['DELETE', '/v1/likes/devs/dev04', [], 5],
    'POST dislikes/devs' => ['POST', '/v1/dislikes/devs/dev04', [], 5],
    'DELETE dislikes/devs' => ['DELETE', '/v1/dislikes/devs/dev04', [], 5],
    'POST likes/channels' => ['POST', '/v1/likes/channels/Canal%20Zeta', [], 5],
    'DELETE likes/channels' => ['DELETE', '/v1/likes/channels/Canal%20Zeta', [], 5],
    'POST dislikes/channels' => ['POST', '/v1/dislikes/channels/Canal%20Zeta', [], 5],
    'DELETE dislikes/channels' => ['DELETE', '/v1/dislikes/channels/Canal%20Zeta', [], 5],
    'GET /likes/devs' => ['GET', '/v1/likes/devs', [], 4],
    'GET /dislikes/devs' => ['GET', '/v1/dislikes/devs', [], 4],
    'GET /feed/subscriptions' => ['GET', '/v1/feed/subscriptions', [], 3],
    'GET /feed/subscriptions página 2' => ['GET', '/v1/feed/subscriptions?page=2', [], 3],
]);

it('o orçamento de GET /likes/devs não cresce com o número de devs curtidos', function (): void {
    $few = countQueries(fn() => $this->getJson('/v1/likes/devs', as_dev('dev05')));

    foreach (range(10, 30) as $n) {
        $this->postJson('/v1/likes/devs/dev' . $n, [], as_dev('dev05'));
    }

    expect(countQueries(fn() => $this->getJson('/v1/likes/devs', as_dev('dev05'))))->toBeLessThanOrEqual(max($few, 4));
});

it('limita a escrita: o 31º POST de criação em um minuto recebe 429 (F5-12), por dev', function (): void {
    fakeGithubUsers();

    $admin = as_admin('dev05');

    foreach (range(1, 30) as $n) {
        $this->postJson('/v1/devs', ['username' => 'dev01'], $admin)->assertCreated();
    }

    $this->postJson('/v1/devs', ['username' => 'dev01'], $admin)->assertStatus(429)->assertHeader('Retry-After')->assertExactJson(['error' => 'Too many requests.']);
    $this->postJson('/v1/channels', ['link' => 'https://x.test', 'title' => 'x', 'category' => 'c'], $admin)->assertStatus(429);
    $this->postJson('/v1/devs', ['username' => 'dev01'], as_dev('dev06'))->assertCreated();
});

it('reações e leituras não são limitadas', function (): void {
    foreach (range(1, 35) as $_) {
        $this->postJson('/v1/likes/devs/dev04', [], as_dev('dev05'))->assertOk();
        $this->getJson('/v1/feed/subscriptions', as_dev('dev05'))->assertOk();
    }
});
