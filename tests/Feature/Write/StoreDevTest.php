<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

it('cria o dev novo com o perfil do GitHub, em minúsculas, e responde 201', function (): void {
    fakeGithubUsers();

    $json = $this->postJson('/v1/devs', ['username' => 'OctoCat'], as_dev('dev05'))->assertCreated()->json();

    expect($json['user'])->toBe('octocat')->and($json['name'])->toBe('Nome OctoCat')->and($json['bio'])->toBe('')
        ->and($json['avatar'])->toBe('https://avatars.example.test/OctoCat.png')
        ->and($json['likes'])->toBe([]);
    expect(DB::table('devs')->where('username', 'octocat')->count())->toBe(1);
    Http::assertSentCount(1);
});

it('dev que já existe: 201 com o dev, sem chamar o GitHub e sem duplicar, mesmo com outra caixa', function (): void {
    fakeGithubUsers();

    $this->postJson('/v1/devs', ['username' => 'DEV01'], as_dev('dev05'))->assertCreated()->assertJsonPath('user', 'dev01')->assertJsonCount(1, 'likes');

    Http::assertNothingSent();
    expect(DB::table('devs')->count())->toBe(35);
});

it('repetir o POST de dev novo não duplica (a segunda vez não chama o GitHub)', function (): void {
    fakeGithubUsers();

    $this->postJson('/v1/devs', ['username' => 'novato'], as_dev('dev05'))->assertCreated();
    $this->postJson('/v1/devs', ['username' => 'novato'], as_dev('dev05'))->assertCreated();

    expect(DB::table('devs')->whereRaw("norm_text(username) = 'novato'")->count())->toBe(1);
    Http::assertSentCount(1);
});

it('username inexistente no GitHub dá 404 (D-6), nunca 500', function (): void {
    fakeGithubUsers(notFound: 'usuario-que-nao-existe');

    $this->postJson('/v1/devs', ['username' => 'usuario-que-nao-existe'], as_dev('dev05'))
        ->assertStatus(404)->assertExactJson(['error' => 'GitHub user not found.']);

    expect(DB::table('devs')->count())->toBe(35);
});

it('GitHub fora do ar dá 502 sem vazar detalhe (D-6)', function (string $case): void {
    match ($case) {
        '500' => Http::fake(['api.github.com/*' => Http::response('boom', 500)]),
        'limite da API' => Http::fake(['api.github.com/*' => Http::response(['message' => 'rate limit'], 403)]),
        'rede' => Http::fake(fn() => throw new Illuminate\Http\Client\ConnectionException('timeout')),
        'sem login' => Http::fake(['api.github.com/*' => Http::response(['name' => 'x'])]),
        default => throw new LogicException($case),
    };

    $response = $this->postJson('/v1/devs', ['username' => 'alguem'], as_dev('dev05'))->assertStatus(502)->assertExactJson(['error' => 'GitHub unavailable.']);

    expect($response->getContent())->not->toContain('boom');
    expect(DB::table('devs')->count())->toBe(35);
})->with(['500', 'limite da API', 'rede', 'sem login']);

it('username fora do formato de login do GitHub dá 422 e nunca chega ao GitHub (F5-7)', function (mixed $username): void {
    fakeGithubUsers();

    $this->postJson('/v1/devs', ['username' => $username], as_dev('dev05'))
        ->assertStatus(422)->assertJsonPath('error', 'Validation failed.')->assertJsonStructure(['errors' => ['username']]);

    Http::assertNothingSent();
})->with([
    'caminho' => '../../orgs/x',
    'barra' => 'a/b',
    'espaço' => 'a b',
    'consulta' => 'a?b=c',
    'começa com hífen' => '-abc',
    '40 caracteres' => str_repeat('a', 40),
    'sublinhado' => 'a_b',
    'acento' => 'açúcar',
    'ausente' => null,
    'lista' => [['a']],
    'número' => 123,
]);

it('aceita login de 39 caracteres e com hífen', function (): void {
    fakeGithubUsers();

    $this->postJson('/v1/devs', ['username' => str_repeat('a', 39)], as_dev('dev05'))->assertCreated();
    $this->postJson('/v1/devs', ['username' => 'a-b-c'], as_dev('dev05'))->assertCreated();
});

it('corpo ausente ou sem username dá 422, não erro de PHP', function (): void {
    $this->postJson('/v1/devs', [], as_dev('dev05'))->assertStatus(422);
    $this->call('POST', '/v1/devs', [], [], [], ['HTTP_AUTHORIZATION' => as_dev('dev05')['Authorization'], 'CONTENT_TYPE' => 'application/json'], 'isto não é json')
        ->assertStatus(422);
});

it('dois pedidos simultâneos do mesmo dev novo viram um registro só (insertOrIgnore)', function (): void {
    $writer = app(App\Features\Dev\Queries\DevWriter::class);
    $profile = new App\Shared\Github\GithubProfile('Corrida', 'Corrida', '', 'https://avatars.example.test/c.png');

    $writer->insertIfMissing($profile);
    $writer->insertIfMissing($profile);

    expect(DB::table('devs')->whereRaw("norm_text(username) = 'corrida'")->count())->toBe(1);
});
