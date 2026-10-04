<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

function idOfChannel(string $name): string
{
    $id = DB::table('channels')->where('name', $name)->value('id');

    return is_string($id) ? $id : '';
}

it('follow e ignore de canal: POST e DELETE devolvem o dev do token com as reações atualizadas', function (string $prefix, string $field): void {
    $zeta = idOfChannel('Canal Zeta');

    $added = $this->postJson("/v1/{$prefix}/channels/Canal%20Zeta", [], as_dev('dev05'))->assertOk()->json();
    expect($added['user'])->toBe('dev05')->and($added[$field])->toBe([$zeta]);

    $removed = $this->deleteJson("/v1/{$prefix}/channels/Canal%20Zeta", [], as_dev('dev05'))->assertOk()->json();
    expect($removed[$field])->toBe([]);
})->with([['likes', 'follow'], ['dislikes', 'ignore']]);

it('é idempotente e follow e ignore do mesmo canal são independentes', function (): void {
    $this->postJson('/v1/likes/channels/Canal%20Zeta', [], as_dev('dev05'));
    $this->postJson('/v1/likes/channels/Canal%20Zeta', [], as_dev('dev05'))->assertOk()->assertJsonCount(1, 'follow');

    $json = $this->postJson('/v1/dislikes/channels/Canal%20Zeta', [], as_dev('dev05'))->json();
    expect($json['follow'])->toHaveCount(1)->and($json['ignore'])->toHaveCount(1);

    expect(DB::table('channel_reactions')->where('dev_id', DB::table('devs')->where('username', 'dev05')->value('id'))->count())->toBe(2);
    $this->deleteJson('/v1/likes/channels/Canal%20Zeta', [], as_dev('dev05'));
    $this->deleteJson('/v1/likes/channels/Canal%20Zeta', [], as_dev('dev05'))->assertOk()->assertJsonCount(1, 'ignore');
});

it('canal inexistente ou apagado dá 400 Channel not exists; o alvo é o nome exato, nunca o link', function (): void {
    $this->postJson('/v1/likes/channels/no-such-channel-zzz', [], as_dev('dev05'))->assertStatus(400)->assertExactJson(['error' => 'Channel not exists']);
    $this->deleteJson('/v1/dislikes/channels/no-such-channel-zzz', [], as_dev('dev05'))->assertStatus(400);
    $this->postJson('/v1/likes/channels/' . rawurlencode('https://youtube.com/alpha'), [], as_dev('dev05'))->assertStatus(400);
    $this->postJson('/v1/likes/channels/Canal', [], as_dev('dev05'))->assertStatus(400);

    DB::table('channels')->where('name', 'Canal Zeta')->update(['deleted_at' => now()]);
    $this->postJson('/v1/likes/channels/Canal%20Zeta', [], as_dev('dev05'))->assertStatus(400)->assertExactJson(['error' => 'Channel not exists']);
});

it('acha o canal sem caixa e sem acento', function (): void {
    $this->postJson('/v1/likes/channels/canal%20zeta', [], as_dev('dev05'))->assertOk()->assertJsonCount(1, 'follow');
});

it('seguir um canal muda o feed de assinaturas e ignorar muda o trending', function (): void {
    $this->postJson('/v1/likes/channels/Canal%20Beta', [], as_dev('dev05'));
    $this->postJson('/v1/dislikes/channels/Canal%20Alpha', [], as_dev('dev05'));

    $this->getJson('/v1/feed/subscriptions', as_dev('dev05'))->assertJsonPath('total', 35);
    $this->getJson('/v1/feed/trending', as_dev('dev05'))->assertJsonPath('total', 35);
});
