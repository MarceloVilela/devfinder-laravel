<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

it('lista o trending: 55 vídeos, página 1 só do Canal Beta', function (): void {
    $json = $this->getJson('/v1/feed/trending?page=1')->assertOk()->json();

    expect($json['total'])->toBe(55)
        ->and($json['itemsPerPage'])->toBe(30)
        ->and($json['totalPages'])->toBe(2)
        ->and($json['docs'])->toHaveCount(30)
        ->and(array_unique(array_column($json['docs'], 'channel')))->toBe(['Canal Beta'])
        ->and($json['docs'][0]['title'])->toBe('Vídeo Beta 35');
});

it('lista a página 2 do trending: 5 do Beta e 20 do Alpha', function (): void {
    $docs = $this->getJson('/v1/feed/trending?page=2')->assertOk()->json('docs');

    expect($docs)->toHaveCount(25)
        ->and(array_count_values(array_column($docs, 'channel')))->toBe(['Canal Beta' => 5, 'Canal Alpha' => 20]);
});

it('serve a última página do trending além do fim', function (): void {
    $this->getJson('/v1/feed/trending?page=99')->assertOk()->assertJsonPath('page', 2)->assertJsonCount(25, 'docs');
});

it('devolve o vídeo com canal resolvido pelo JOIN', function (): void {
    $this->getJson('/v1/video/vidalpha01')->assertOk()->assertJson([
        'title' => 'Vídeo Alpha 01',
        'url' => 'https://www.youtube.com/watch?v=vidalpha01',
        'channel' => 'Canal Alpha',
        'channel_url' => 'https://youtube.com/alpha',
        'viewnum' => null,
        'date' => null,
    ]);
});

it('devolve 200 com null quando o vídeo não existe (F3-2)', function (): void {
    expect($this->get('/v1/video/does-not-exist', ['Accept' => 'application/json'])->assertOk()->getContent())->toBe('null');
});

it('lista os vídeos de um canal: Beta tem 30 + 5, Alpha tem 20', function (): void {
    $this->getJson('/v1/feed/channel?channel_name=Canal%20Beta&page=1')->assertJsonPath('total', 35)->assertJsonCount(30, 'docs');
    $this->getJson('/v1/feed/channel?channel_name=Canal%20Beta&page=2')->assertJsonCount(5, 'docs');
    $this->getJson('/v1/feed/channel?channel_name=Canal%20Alpha')->assertJsonPath('total', 20)->assertJsonPath('totalPages', 1);
});

it('acha o canal do feed por link e sem caixa', function (): void {
    $this->getJson('/v1/feed/channel?channel_name=' . rawurlencode('https://youtube.com/alpha'))->assertJsonPath('total', 20);
    $this->getJson('/v1/feed/channel?channel_name=canal%20alpha')->assertJsonPath('total', 20);
});

it('devolve lista vazia para canal inexistente, ausente ou vazio (F3-2)', function (string $query): void {
    $this->getJson("/v1/feed/channel{$query}")->assertOk()->assertExactJson([
        'docs' => [], 'total' => 0, 'itemsPerPage' => 30, 'page' => 1, 'totalPages' => 1,
    ]);
})->with(['?channel_name=NaoExiste', '', '?channel_name=', '?channel_name[]=x']);

it('não lista vídeo apagado nem vídeo de canal apagado (F3-8)', function (): void {
    DB::table('videos')->where('youtube_id', 'vidbeta35')->update(['deleted_at' => now()]);
    $this->getJson('/v1/feed/trending')->assertJsonPath('total', 54);

    DB::table('channels')->where('name', 'Canal Alpha')->update(['deleted_at' => now()]);
    $this->getJson('/v1/feed/trending')->assertJsonPath('total', 34);
    expect($this->get('/v1/video/vidalpha01')->getContent())->toBe('null');
    $this->getJson('/v1/feed/channel?channel_name=Canal%20Alpha')->assertJsonPath('total', 0);
});
