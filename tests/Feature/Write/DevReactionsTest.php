<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

function idOfDev(string $username): string
{
    $id = DB::table('devs')->where('username', $username)->value('id');

    return is_string($id) ? $id : '';
}

it('POST e DELETE de like/dislike em dev: a resposta é o dev do token com as reações já atualizadas', function (string $prefix, string $field): void {
    $target = idOfDev('dev04');

    $added = $this->postJson("/v1/{$prefix}/devs/dev04", [], as_dev('dev05'))->assertOk()->json();
    expect($added['user'])->toBe('dev05')->and($added[$field])->toBe([$target]);

    $removed = $this->deleteJson("/v1/{$prefix}/devs/dev04", [], as_dev('dev05'))->assertOk()->json();
    expect($removed[$field])->toBe([]);
})->with([['likes', 'likes'], ['dislikes', 'deslikes']]);

it('é idempotente: repetir o POST não duplica nem erra, e remover o que não existe não erra', function (): void {
    $this->postJson('/v1/likes/devs/dev04', [], as_dev('dev05'))->assertOk();
    $this->postJson('/v1/likes/devs/dev04', [], as_dev('dev05'))->assertOk()->assertJsonCount(1, 'likes');
    expect(DB::table('dev_reactions')->where('dev_id', idOfDev('dev05'))->count())->toBe(1);

    $this->deleteJson('/v1/likes/devs/dev04', [], as_dev('dev05'))->assertOk();
    $this->deleteJson('/v1/likes/devs/dev04', [], as_dev('dev05'))->assertOk()->assertJsonPath('likes', []);
});

it('like e dislike do mesmo par são independentes (F5-3)', function (): void {
    $this->postJson('/v1/likes/devs/dev04', [], as_dev('dev05'));
    $json = $this->postJson('/v1/dislikes/devs/dev04', [], as_dev('dev05'))->json();

    expect($json['likes'])->toHaveCount(1)->and($json['deslikes'])->toHaveCount(1);

    $this->deleteJson('/v1/likes/devs/dev04', [], as_dev('dev05'))->assertJsonCount(1, 'deslikes')->assertJsonCount(0, 'likes');
});

it('reagir a si mesmo não grava nada e responde 200 (F5-4)', function (): void {
    $this->postJson('/v1/likes/devs/dev05', [], as_dev('dev05'))->assertOk()->assertJsonPath('likes', []);
    $this->postJson('/v1/dislikes/devs/DEV05', [], as_dev('dev05'))->assertOk()->assertJsonPath('deslikes', []);

    expect(DB::table('dev_reactions')->where('dev_id', idOfDev('dev05'))->count())->toBe(0);
});

it('alvo inexistente ou apagado dá 400 Dev not exists (F5-5)', function (): void {
    $this->postJson('/v1/likes/devs/no-such-dev-zzz', [], as_dev('dev05'))->assertStatus(400)->assertExactJson(['error' => 'Dev not exists']);
    $this->deleteJson('/v1/dislikes/devs/no-such-dev-zzz', [], as_dev('dev05'))->assertStatus(400);

    DB::table('devs')->where('username', 'dev04')->update(['deleted_at' => now()]);
    $this->postJson('/v1/likes/devs/dev04', [], as_dev('dev05'))->assertStatus(400)->assertExactJson(['error' => 'Dev not exists']);
});

it('acha o alvo sem caixa e sem acento', function (): void {
    $this->postJson('/v1/likes/devs/DEV04', [], as_dev('dev05'))->assertOk()->assertJsonCount(1, 'likes');
});

it('toda reação e todo GET de reação exigem token (401)', function (string $method, string $path): void {
    $this->json($method, "/v1{$path}")->assertStatus(401)->assertExactJson(['error' => 'Token not provided.']);
    $ghost = app(App\Features\Auth\Support\TokenCodec::class)->issue('fantasma');
    $this->json($method, "/v1{$path}", [], ['Authorization' => "Bearer {$ghost}"])->assertStatus(401)->assertExactJson(['error' => 'Token invalid.']);
})->with([
    ['POST', '/likes/devs/dev04'], ['DELETE', '/likes/devs/dev04'], ['POST', '/dislikes/devs/dev04'], ['DELETE', '/dislikes/devs/dev04'],
    ['POST', '/likes/channels/Canal%20Zeta'], ['DELETE', '/likes/channels/Canal%20Zeta'],
    ['POST', '/dislikes/channels/Canal%20Zeta'], ['DELETE', '/dislikes/channels/Canal%20Zeta'],
    ['GET', '/likes/devs'], ['GET', '/dislikes/devs'], ['GET', '/feed/subscriptions'],
    ['POST', '/devs'], ['POST', '/channels'], ['POST', '/video'],
]);

it('a reação grava para o dono do token, nunca para outro', function (): void {
    $this->postJson('/v1/likes/devs/dev04', [], as_dev('dev05'));

    $this->getJson('/v1/devs/dev06')->assertJsonPath('likes', []);
    $this->getJson('/v1/me', as_dev('dev06'))->assertJsonPath('likes', []);
    expect($this->getJson('/v1/me', as_dev('dev05'))->json('likes'))->toHaveCount(1);
});

it('lista os devs curtidos e descurtidos', function (): void {
    foreach (['dev09', 'dev04', 'dev07'] as $u) {
        $this->postJson('/v1/likes/devs/' . $u, [], as_dev('dev05'));
    }
    $this->postJson('/v1/dislikes/devs/dev08', [], as_dev('dev05'));

    $liked = $this->getJson('/v1/likes/devs', as_dev('dev05'))->assertOk()->json();
    expect(array_column($liked, 'user'))->toBe(['dev04', 'dev07', 'dev09'])
        ->and(array_column($this->getJson('/v1/dislikes/devs', as_dev('dev05'))->json(), 'user'))->toBe(['dev08']);

    // cada item traz as reações do próprio dev listado
    $this->postJson('/v1/likes/devs/dev01', [], as_dev('dev05'));
    $item = current(array_filter($this->getJson('/v1/likes/devs', as_dev('dev05'))->json(), static fn(array $dev): bool => $dev['user'] === 'dev01'));
    expect($item['likes'])->toHaveCount(1)->and($item['follow'])->toHaveCount(1);
});

it('a lista não traz dev apagado e começa vazia', function (): void {
    $this->getJson('/v1/likes/devs', as_dev('dev05'))->assertOk()->assertExactJson([]);

    $this->postJson('/v1/likes/devs/dev04', [], as_dev('dev05'));
    DB::table('devs')->where('username', 'dev04')->update(['deleted_at' => now()]);

    $this->getJson('/v1/likes/devs', as_dev('dev05'))->assertExactJson([]);
});

it('o like de dev01 em dev02 do dataset aparece na lista dele', function (): void {
    expect(array_column($this->getJson('/v1/likes/devs', as_dev('dev01'))->json(), 'user'))->toBe(['dev02'])
        ->and(array_column($this->getJson('/v1/dislikes/devs', as_dev('dev01'))->json(), 'user'))->toBe(['dev03']);
});
