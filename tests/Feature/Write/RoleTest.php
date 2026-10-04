<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

/** @return array<string, mixed> */
function channelBody(): array
{
    return ['link' => 'https://x.test/rbac', 'title' => 'Canal RBAC', 'category' => 'c', 'tags' => ['a']];
}

/** @return array<string, mixed> */
function videoBody(): array
{
    return ['title' => 't', 'url' => 'https://www.youtube.com/watch?v=rbac0000001', 'channel' => 'Canal Alpha', 'channel_url' => 'x'];
}

it('todo dev nasce USER (o default da coluna), inclusive os criados por login, POST /devs e userGithub', function (): void {
    fakeGithubUsers();
    expect(DB::table('devs')->where('role', '<>', 'USER')->count())->toBe(0);

    $this->postJson('/v1/devs', ['username' => 'novato'], as_dev('dev05'))->assertCreated();
    $this->postJson('/v1/channels', channelBody() + ['userGithub' => 'dono'], as_admin('dev06'))->assertCreated();

    expect(DB::table('devs')->whereIn('username', ['novato', 'dono'])->pluck('role')->all())->toBe(['USER', 'USER']);
});

it('USER não cria nem atualiza canal e não cria vídeo: 403 Forbidden, sem gravar nada', function (): void {
    Http::fake();

    $this->postJson('/v1/channels', channelBody(), as_dev('dev05'))->assertStatus(403)->assertExactJson(['error' => 'Forbidden.']);
    $this->postJson('/v1/channels', ['link' => 'https://youtube.com/alpha', 'title' => 'Canal Alpha', 'category' => 'x'], as_dev('dev05'))->assertStatus(403);
    $this->postJson('/v1/video', videoBody(), as_dev('dev05'))->assertStatus(403)->assertExactJson(['error' => 'Forbidden.']);

    expect(DB::table('channels')->count())->toBe(3)
        ->and(DB::table('channels')->where('name', 'Canal Alpha')->value('category'))->toBe('Tecnologia')
        ->and(DB::table('videos')->count())->toBe(55)
        ->and(DB::table('tags')->count())->toBe(3);
    Http::assertNothingSent();
});

it('o 403 vem antes da validação: USER com corpo inválido recebe 403, não 422', function (): void {
    $this->postJson('/v1/channels', [], as_dev('dev05'))->assertStatus(403);
    $this->postJson('/v1/video', [], as_dev('dev05'))->assertStatus(403);
});

it('ADMIN cria e atualiza canal e cria vídeo', function (): void {
    $admin = as_admin('dev05');

    $this->postJson('/v1/channels', channelBody(), $admin)->assertCreated();
    $this->postJson('/v1/channels', channelBody() + ['description' => 'nova'], $admin)->assertOk()->assertJsonPath('description', 'nova');
    $this->postJson('/v1/video', videoBody(), $admin)->assertCreated();
});

it('sem token continua 401 (a autenticação vem antes do papel)', function (): void {
    $this->postJson('/v1/channels', channelBody())->assertStatus(401)->assertExactJson(['error' => 'Token not provided.']);
    $this->postJson('/v1/video', videoBody(), ['Authorization' => 'Bearer lixo'])->assertStatus(401)->assertExactJson(['error' => 'Token invalid.']);
});

it('promover e rebaixar no banco vale na requisição seguinte, com o mesmo token', function (): void {
    $token = as_dev('dev05');

    $this->postJson('/v1/channels', channelBody(), $token)->assertStatus(403);

    DB::table('devs')->where('username', 'dev05')->update(['role' => 'ADMIN']);
    $this->postJson('/v1/channels', channelBody(), $token)->assertCreated();

    DB::table('devs')->where('username', 'dev05')->update(['role' => 'USER']);
    $this->postJson('/v1/channels', channelBody() + ['description' => 'x'], $token)->assertStatus(403);
});

it('ser ADMIN é do dev, não do token: outro dev com token válido continua USER', function (): void {
    $admin = as_admin('dev05');
    $user = as_dev('dev06');

    $this->postJson('/v1/channels', channelBody(), $admin)->assertCreated();
    $this->postJson('/v1/channels', channelBody(), $user)->assertStatus(403);
});

it('o que não é de canal ou vídeo segue aberto a USER: reações, POST /devs, listas e assinaturas', function (): void {
    fakeGithubUsers();
    $user = as_dev('dev05');

    $this->postJson('/v1/likes/devs/dev04', [], $user)->assertOk();
    $this->postJson('/v1/likes/channels/Canal%20Zeta', [], $user)->assertOk();
    $this->postJson('/v1/devs', ['username' => 'octocat'], $user)->assertCreated();
    $this->getJson('/v1/likes/devs', $user)->assertOk();
    $this->getJson('/v1/feed/subscriptions', $user)->assertOk();
    $this->getJson('/v1/me', $user)->assertOk();
});

it('o papel não aparece no JSON (o contrato do Dev não muda)', function (): void {
    $keys = array_keys($this->getJson('/v1/me', as_admin('dev05'))->json());

    expect($keys)->toBe(['_id', 'name', 'user', 'bio', 'avatar', 'likes', 'deslikes', 'follow', 'ignore', 'createdAt', 'updatedAt']);
    expect(array_keys($this->getJson('/v1/devs/dev05')->json()))->not->toContain('role');
});

it('o banco só aceita USER ou ADMIN', function (): void {
    expect(fn() => DB::table('devs')->where('username', 'dev05')->update(['role' => 'SUPERUSER']))->toThrow(QueryException::class);
    expect(fn() => DB::table('devs')->where('username', 'dev05')->update(['role' => 'admin']))->toThrow(QueryException::class);
});

it('o 403 de USER não gasta o limite de escrita (o papel é checado antes do limiter)', function (): void {
    foreach (range(1, 35) as $_) {
        $this->postJson('/v1/channels', channelBody(), as_dev('dev05'))->assertStatus(403);
    }
});

it('a escrita do ADMIN continua com limite por dev', function (): void {
    $admin = as_admin('dev05');

    foreach (range(1, 30) as $n) {
        $this->postJson('/v1/video', videoBody() + ['url' => "https://www.youtube.com/watch?v=lim{$n}"], $admin);
    }

    $this->postJson('/v1/video', videoBody(), $admin)->assertStatus(429);
});
