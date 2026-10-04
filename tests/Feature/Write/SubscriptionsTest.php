<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

it('dev01 segue o Canal Alpha: vê só os 20 vídeos dele, do mais recente ao mais antigo', function (): void {
    $json = $this->getJson('/v1/feed/subscriptions', as_dev('dev01'))->assertOk()->json();

    expect($json['total'])->toBe(20)
        ->and($json['itemsPerPage'])->toBe(30)
        ->and($json['page'])->toBe(1)
        ->and($json['totalPages'])->toBe(1)
        ->and(array_unique(array_column($json['docs'], 'channel')))->toBe(['Canal Alpha'])
        ->and($json['docs'][0]['title'])->toBe('Vídeo Alpha 20');
});

it('quem não segue nada vê a lista vazia, com o envelope do contrato', function (): void {
    $this->getJson('/v1/feed/subscriptions', as_dev('dev05'))->assertOk()->assertExactJson([
        'docs' => [], 'total' => 0, 'itemsPerPage' => 30, 'page' => 1, 'totalPages' => 1,
    ]);
});

it('seguir o Canal Beta soma 35 vídeos e a paginação segue o trending (P-3)', function (): void {
    $this->postJson('/v1/likes/channels/Canal%20Beta', [], as_dev('dev01'));

    $this->getJson('/v1/feed/subscriptions', as_dev('dev01'))->assertJsonPath('total', 55)->assertJsonCount(30, 'docs');
    $this->getJson('/v1/feed/subscriptions?page=2', as_dev('dev01'))->assertJsonCount(25, 'docs');
    $this->getJson('/v1/feed/subscriptions?page=99', as_dev('dev01'))->assertJsonPath('page', 2);
    $this->getJson('/v1/feed/subscriptions?page=abc', as_dev('dev01'))->assertJsonPath('page', 1);
});

it('follow de canal apagado ou vídeo apagado não aparece, e ignore não tira da assinatura', function (): void {
    DB::table('videos')->where('youtube_id', 'vidalpha20')->update(['deleted_at' => now()]);
    $this->getJson('/v1/feed/subscriptions', as_dev('dev01'))->assertJsonPath('total', 19);

    $this->postJson('/v1/dislikes/channels/Canal%20Alpha', [], as_dev('dev01'));
    $this->getJson('/v1/feed/subscriptions', as_dev('dev01'))->assertJsonPath('total', 19);

    DB::table('channels')->where('name', 'Canal Alpha')->update(['deleted_at' => now()]);
    $this->getJson('/v1/feed/subscriptions', as_dev('dev01'))->assertJsonPath('total', 0);
});

it('cada dev vê só as próprias assinaturas', function (): void {
    expect($this->getJson('/v1/feed/subscriptions', as_dev('dev01'))->json('total'))->toBe(20)
        ->and($this->getJson('/v1/feed/subscriptions', as_dev('dev02'))->json('total'))->toBe(0);
});

it('um vídeo novo de um canal seguido entra no topo', function (): void {
    $this->postJson('/v1/video', ['title' => 'Novo no Alpha', 'url' => 'https://www.youtube.com/watch?v=novoAlpha01', 'channel' => 'Canal Alpha', 'channel_url' => 'https://youtube.com/alpha'], as_admin('dev05'));

    $this->getJson('/v1/feed/subscriptions', as_dev('dev01'))->assertJsonPath('total', 21)->assertJsonPath('docs.0.title', 'Novo no Alpha');
});
