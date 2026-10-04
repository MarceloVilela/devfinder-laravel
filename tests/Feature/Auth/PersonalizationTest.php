<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

it('GET /devs autenticado tira o próprio dev e quem recebeu like ou dislike (dev01: 35 - 3 = 32)', function (): void {
    $json = $this->getJson('/v1/devs', as_dev('dev01'))->assertOk()->json();
    $users = array_column($json['docs'], 'user');

    expect($json['total'])->toBe(32)
        ->and($json['totalPages'])->toBe(2)
        ->and($users)->not->toContain('dev01', 'dev02', 'dev03')
        ->and($users[0])->toBe('dev35');

    expect($this->getJson('/v1/devs?page=2', as_dev('dev01'))->json('docs'))->toHaveCount(2);
});

it('GET /devs de dev sem reações só tira ele mesmo (35 - 1 = 34)', function (): void {
    $json = $this->getJson('/v1/devs', as_dev('dev05'))->json();

    expect($json['total'])->toBe(34)->and(array_column($json['docs'], 'user'))->not->toContain('dev05');
});

it('só like e dislike excluem; follow e ignore de canal não mexem em /devs', function (): void {
    DB::table('dev_reactions')->delete();

    $this->getJson('/v1/devs', as_dev('dev01'))->assertJsonPath('total', 34);
});

it('o anônimo continua vendo os 35', function (): void {
    $this->getJson('/v1/devs')->assertJsonPath('total', 35);
});

it('feed/trending tira os canais ignorados: por token, por ?user= e os dois', function (): void {
    $this->getJson('/v1/feed/trending', as_dev('dev01'))->assertJsonPath('total', 20);
    $this->getJson('/v1/feed/trending?user=dev01')->assertJsonPath('total', 20);
    $this->getJson('/v1/feed/trending?user=DEV01')->assertJsonPath('total', 20);
});

it('feed/trending de quem não ignora nada, ?user= desconhecido ou vazio fica com os 55', function (): void {
    $this->getJson('/v1/feed/trending', as_dev('dev05'))->assertJsonPath('total', 55);
    $this->getJson('/v1/feed/trending?user=fantasma')->assertJsonPath('total', 55);
    $this->getJson('/v1/feed/trending?user=')->assertJsonPath('total', 55);
    $this->getJson('/v1/feed/trending?user[]=dev01')->assertJsonPath('total', 55);
});

it('o token vale mais que ?user=', function (): void {
    $this->getJson('/v1/feed/trending?user=dev01', as_dev('dev05'))->assertJsonPath('total', 55);
});

it('feed/trending personalizado só tem vídeos do canal não ignorado', function (): void {
    $docs = $this->getJson('/v1/feed/trending', as_dev('dev01'))->json('docs');

    expect(array_unique(array_column($docs, 'channel')))->toBe(['Canal Alpha']);
});

it('a personalização respeita o orçamento de queries (+1 do token, +1 do ?user=)', function (string $path, bool $auth, int $budget): void {
    $headers = $auth ? as_dev('dev01') : [];

    expect(countQueries(fn() => $this->getJson($path, $headers)->assertOk()))->toBeLessThanOrEqual($budget);
})->with([
    'GET /devs com token (4 + 1)' => ['/v1/devs?page=1', true, 5],
    'GET /devs com token, página 2' => ['/v1/devs?page=2', true, 5],
    'trending com token (2 + 1)' => ['/v1/feed/trending', true, 3],
    'trending com ?user= (2 + 1)' => ['/v1/feed/trending?user=dev01', false, 3],
    'GET /me (3)' => ['/v1/me', true, 3],
    'GET /devs/{u} com token (3 + 1)' => ['/v1/devs/dev01', true, 4],
]);
