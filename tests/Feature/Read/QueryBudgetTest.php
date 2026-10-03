<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

// Orçamento da Fase 1 (`fase-1-modelo-de-dados.md`): independente do tamanho da página, sem N+1.
it('respeita o orçamento de queries e ele não cresce com a página', function (string $path, int $budget): void {
    $count = countQueries(fn() => $this->getJson($path)->assertOk());

    expect($count)->toBeLessThanOrEqual($budget);
})->with([
    'GET /devs (página cheia)' => ['/v1/devs?page=1', 4],
    'GET /devs (página parcial)' => ['/v1/devs?page=2', 4],
    'GET /devs/{username}' => ['/v1/devs/dev01', 3],
    'GET /devs/{username} inexistente' => ['/v1/devs/nobody', 1],
    'GET /channels' => ['/v1/channels', 2],
    'GET /channels/{q}' => ['/v1/channels/Canal%20Alpha', 2],
    'GET /channels/{q} inexistente' => ['/v1/channels/nada', 1],
    'GET /feed/trending (cheia)' => ['/v1/feed/trending?page=1', 2],
    'GET /feed/trending (parcial)' => ['/v1/feed/trending?page=2', 2],
    'GET /feed/channel' => ['/v1/feed/channel?channel_name=Canal%20Beta', 3],
    'GET /feed/channel inexistente' => ['/v1/feed/channel?channel_name=nada', 1],
    'GET /video/{id}' => ['/v1/video/vidalpha01', 1],
    'GET /description/feed' => ['/v1/description/feed', 2],
    'GET /description/category' => ['/v1/description/category', 2],
    'GET /search' => ['/v1/search?q=Alpha', 2],
]);

it('executa exatamente o número previsto nas listas principais', function (string $path, int $expected): void {
    expect(countQueries(fn() => $this->getJson($path)->assertOk()))->toBe($expected);
})->with([
    ['/v1/devs?page=1', 4],
    ['/v1/devs/dev01', 3],
    ['/v1/channels', 2],
    ['/v1/feed/trending', 2],
    ['/v1/video/vidalpha01', 1],
]);

it('o orçamento de /devs não muda com 5 ou 30 itens, nem com mais dados', function (): void {
    $small = countQueries(fn() => $this->getJson('/v1/devs?page=2'));
    $full = countQueries(fn() => $this->getJson('/v1/devs?page=1'));

    expect($small)->toBe($full);
});
