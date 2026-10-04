<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

/** @return array<string, mixed> */
function refreshBody(): array
{
    return ['record' => [
        ['title' => 'Video novo de teste HTTP — Canal Alpha', 'url' => 'https://www.youtube.com/watch?v=HTTPNEWVIDEO1', 'channel' => 'Canal Alpha', 'channel_url' => 'https://youtube.com/alpha', 'thumbnail' => 'https://img.youtube.com/vi/HTTPNEWVIDEO1/hqdefault.jpg'],
        ['title' => 'Vídeo Alpha 01', 'url' => 'https://www.youtube.com/watch?v=vidalpha01', 'channel' => 'Canal Alpha', 'channel_url' => 'https://youtube.com/alpha', 'thumbnail' => 'https://i.ytimg.com/vi/vidalpha01/hqdefault.jpg'],
        ['title' => 'Video de canal inexistente HTTP', 'url' => 'https://www.youtube.com/watch?v=DOESNOTMATTERHTTP', 'channel' => 'Canal Inexistente XYZ', 'channel_url' => 'https://www.youtube.com/canal-inexistente', 'thumbnail' => ''],
    ]];
}

it('exige token e papel ADMIN (F6-6)', function (): void {
    $this->postJson('/v1/video/refresh', refreshBody())->assertStatus(401)->assertExactJson(['error' => 'Token not provided.']);
    $this->postJson('/v1/video/refresh', refreshBody(), as_dev('dev05'))->assertStatus(403)->assertExactJson(['error' => 'Forbidden.']);

    expect(DB::table('videos')->count())->toBe(55);
});

it('lote misto: o resumo no formato do contrato, 1 novo, 1 encontrado, 1 erro', function (): void {
    $json = $this->postJson('/v1/video/refresh', refreshBody(), as_admin('dev05'))->assertOk()->json();

    expect(array_keys($json))->toBe(['videosAdded', 'videosFounded', 'errors'])
        ->and($json['videosAdded'])->toHaveCount(1)->and($json['videosAdded'][0]['channel'])->toBe('Canal Alpha')
        ->and($json['videosAdded'][0]['thumbnail'])->toBe('https://img.youtube.com/vi/HTTPNEWVIDEO1/hqdefault.jpg')
        ->and($json['videosFounded'])->toHaveCount(1)->and($json['videosFounded'][0]['title'])->toBe('Vídeo Alpha 01')
        ->and($json['errors'])->toHaveCount(1)->and($json['errors'][0]['errorMessage'])->toBe('channel(Canal Inexistente XYZ) not found, for: Video de canal inexistente HTTP');
});

it('reexecutar o mesmo lote é idempotente', function (): void {
    $admin = as_admin('dev05');
    $this->postJson('/v1/video/refresh', refreshBody(), $admin)->assertOk();

    $second = $this->postJson('/v1/video/refresh', refreshBody(), $admin)->assertOk()->json();

    expect($second['videosAdded'])->toBe([])->and($second['videosFounded'])->toHaveCount(2)->and($second['errors'])->toHaveCount(1);
    expect(DB::table('videos')->count())->toBe(56);
});

it('record ausente, nulo ou vazio dá 200 com listas vazias (paridade com o v1)', function (array $body): void {
    $this->postJson('/v1/video/refresh', $body, as_admin('dev05'))->assertOk()->assertExactJson(['videosAdded' => [], 'videosFounded' => [], 'errors' => []]);
})->with([[[]], [['record' => []]], [['record' => null]]]);

it('mais de 200 candidatos dá 422; record que não é lista também', function (): void {
    $admin = as_admin('dev05');

    $this->postJson('/v1/video/refresh', ['record' => array_fill(0, 201, ['title' => 't'])], $admin)->assertStatus(422)->assertJsonStructure(['errors' => ['record']]);
    $this->postJson('/v1/video/refresh', ['record' => 'texto'], $admin)->assertStatus(422);
    $this->postJson('/v1/video/refresh', ['record' => array_fill(0, 200, ['title' => 't'])], $admin)->assertOk();
});

it('itens de formato errado viram errors, não 500', function (): void {
    $json = $this->postJson('/v1/video/refresh', ['record' => ['texto', 7, null, ['title' => 'sem url'], ['url' => ['a']]]], as_admin('dev05'))->assertOk()->json();

    expect($json['errors'])->toHaveCount(5)->and($json['videosAdded'])->toBe([]);
});

it('respeita o orçamento de queries do lote', function (): void {
    $admin = as_admin('dev05');
    $record = array_map(static fn(int $n): array => ['title' => "Lote {$n}", 'url' => sprintf('https://www.youtube.com/watch?v=LOTE%07d', $n), 'channel' => 'Canal Alpha', 'channel_url' => 'https://youtube.com/alpha'], range(1, 20));

    // auth (1) + canais distintos (1) + urls (1) + inserções (20) + leitura (1) + papel já vem do auth
    expect(countQueries(fn() => $this->postJson('/v1/video/refresh', ['record' => $record], $admin)->assertOk()))->toBeLessThanOrEqual(1 + 1 + 1 + 20 + 1 + 1);
});

it('a escrita é limitada por dev (30 por minuto)', function (): void {
    $admin = as_admin('dev05');

    foreach (range(1, 30) as $_) {
        $this->postJson('/v1/video/refresh', [], $admin)->assertOk();
    }

    $this->postJson('/v1/video/refresh', [], $admin)->assertStatus(429);
});
