<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function newChannel(array $overrides = []): array
{
    return $overrides + [
        'link' => 'https://www.youtube.com/channel/UCFASE5TEST',
        'title' => 'Canal Fase5 Teste',
        'description' => 'desc original',
        'tags' => ['fase5'],
        'category' => 'Fase5 Design 🎨',
    ];
}

it('cria o canal novo (201): emoji sai da categoria sem cortar o espaço, tags novas, likes e deslikes vazios', function (): void {
    $json = $this->postJson('/v1/channels', newChannel(), as_admin('dev05'))->assertCreated()->json();

    expect($json['name'])->toBe('Canal Fase5 Teste')
        ->and($json['category'])->toBe('Fase5 Design ')
        ->and($json['tags'])->toBe(['fase5'])
        ->and($json['description'])->toBe('desc original')
        ->and($json['userGithub'])->toBeNull()
        ->and($json['avatar'])->toBeNull()
        ->and($json['likes'])->toBe([])
        ->and($json['_id'])->toBeString();

    $this->getJson('/v1/channels/Canal%20Fase5%20Teste')->assertOk()->assertJsonPath('tags', ['fase5']);
});

it('o mesmo título e link de novo atualiza (200) e SUBSTITUI as tags por inteiro (paridade)', function (): void {
    $first = $this->postJson('/v1/channels', newChannel(), as_admin('dev05'))->assertCreated()->json();

    $second = $this->postJson('/v1/channels', newChannel(['description' => 'desc atualizada', 'tags' => ['fase5', 'atualizado'], 'category' => 'Categoria Nova']), as_admin('dev05'))
        ->assertOk()->json();

    expect($second['_id'])->toBe($first['_id'])
        ->and($second['description'])->toBe('desc atualizada')
        ->and($second['category'])->toBe('Categoria Nova')
        ->and($second['tags'])->toBe(['atualizado', 'fase5']);
    expect(DB::table('channels')->where('name', 'Canal Fase5 Teste')->count())->toBe(1);

    $third = $this->postJson('/v1/channels', newChannel(['tags' => ['so-esta']]), as_admin('dev05'))->assertOk()->json();
    expect($third['tags'])->toBe(['so-esta']);
});

it('atualizar sem description e avatar zera os dois (paridade com o v1)', function (): void {
    $this->postJson('/v1/channels', newChannel(['avatar' => 'https://example.test/a.png']), as_admin('dev05'))->assertCreated();

    $json = $this->postJson('/v1/channels', newChannel(['description' => null]), as_admin('dev05'))->assertOk()->json();

    expect($json['description'])->toBeNull()->and($json['avatar'])->toBeNull();
});

it('acha o canal existente por "contém", como o v1 (F5-1): um título curto atualiza o canal cujo nome o contém', function (): void {
    $json = $this->postJson('/v1/channels', newChannel(['title' => 'Alpha', 'link' => 'https://youtube.com/novo-link-zzz']), as_admin('dev05'))
        ->assertOk()->json();

    expect($json['name'])->toBe('Alpha');
    expect(DB::table('channels')->count())->toBe(3);
    expect(DB::table('channels')->where('name', 'Canal Alpha')->count())->toBe(0);
});

it('o "contém" ignora caixa e acento e trata % e _ como texto', function (): void {
    $this->postJson('/v1/channels', newChannel(['title' => 'CANAL ÁLPHA']), as_admin('dev05'))->assertOk();
    expect(DB::table('channels')->count())->toBe(3);

    $this->postJson('/v1/channels', newChannel(['title' => '%', 'link' => 'https://youtube.com/pct']), as_admin('dev05'))->assertCreated();
    expect(DB::table('channels')->count())->toBe(4);
});

it('tags repetidas e vazias não viram vínculos extras, e a tag que já existe é reaproveitada', function (): void {
    $json = $this->postJson('/v1/channels', newChannel(['tags' => ['react', 'react', '', 'nova', 'nova']]), as_admin('dev05'))->assertCreated()->json();

    expect($json['tags'])->toBe(['nova', 'react']);
    expect(DB::table('tags')->where('name', 'react')->count())->toBe(1);
    expect(DB::table('tags')->count())->toBe(4);
});

it('userGithub: cria o dev do GitHub só na criação do canal', function (): void {
    fakeGithubUsers();

    $json = $this->postJson('/v1/channels', newChannel(['userGithub' => 'octocat']), as_admin('dev05'))->assertCreated()->json();

    expect($json['userGithub'])->toBe('octocat');
    expect(DB::table('devs')->where('username', 'octocat')->count())->toBe(1);
    Http::assertSentCount(1);

    $this->postJson('/v1/channels', newChannel(['userGithub' => 'outro']), as_admin('dev05'))->assertOk();
    Http::assertSentCount(1);
    expect(DB::table('devs')->where('username', 'outro')->count())->toBe(0);
});

it('userGithub: reaproveita o dev que já existe, sem duplicar', function (): void {
    fakeGithubUsers();

    $this->postJson('/v1/channels', newChannel(['userGithub' => 'dev01']), as_admin('dev05'))->assertCreated();

    expect(DB::table('devs')->whereRaw("norm_text(username) = 'dev01'")->count())->toBe(1);
});

it('GitHub 404 ou fora do ar não derruba a criação do canal (F5-9)', function (string $case): void {
    match ($case) {
        '404' => fakeGithubUsers(notFound: 'fantasma'),
        '500' => Http::fake(['api.github.com/*' => Http::response('boom', 500)]),
        'rede' => Http::fake(fn() => throw new Illuminate\Http\Client\ConnectionException('timeout')),
        default => throw new LogicException($case),
    };

    $this->postJson('/v1/channels', newChannel(['userGithub' => 'fantasma']), as_admin('dev05'))->assertCreated()->assertJsonPath('userGithub', 'fantasma');

    expect(DB::table('devs')->where('username', 'fantasma')->count())->toBe(0);
    expect(DB::table('channels')->where('name', 'Canal Fase5 Teste')->count())->toBe(1);
})->with(['404', '500', 'rede']);

it('a gravação é uma transação: se uma etapa falha, não sobra canal nem tag pela metade (F5-8)', function (): void {
    DB::unprepared('create or replace function falha_vinculo() returns trigger language plpgsql as $$ begin raise exception \'falha de teste\'; end $$');
    DB::unprepared('create trigger falha_vinculo before insert on channel_tag for each row execute function falha_vinculo()');

    try {
        $this->postJson('/v1/channels', newChannel(), as_admin('dev05'))->assertStatus(500);
    } finally {
        DB::unprepared('drop trigger falha_vinculo on channel_tag');
        DB::unprepared('drop function falha_vinculo()');
    }

    expect(DB::table('channels')->where('name', 'Canal Fase5 Teste')->count())->toBe(0);
    expect(DB::table('tags')->where('name', 'fase5')->count())->toBe(0);
});

it('nome ou link que colide com OUTRO canal ao atualizar dá 409, nunca 500 (F5-14)', function (): void {
    $this->postJson('/v1/channels', newChannel(['title' => 'Canal Solto', 'link' => 'https://youtube.com/solto']), as_admin('dev05'))->assertCreated();

    // "Canal Solto" casa por "contém" e vira a atualização; o novo link já é de Canal Beta
    $this->postJson('/v1/channels', newChannel(['title' => 'Canal Solto', 'link' => 'https://youtube.com/beta']), as_admin('dev05'))
        ->assertStatus(409)->assertExactJson(['error' => 'Channel already exists.']);

    expect(DB::table('channels')->where('name', 'Canal Solto')->value('link'))->toBe('https://youtube.com/solto');
});

it('422 com errors por campo para o que falta ou passa do limite (D-3)', function (array $body, string $field): void {
    $this->postJson('/v1/channels', $body, as_admin('dev05'))
        ->assertStatus(422)->assertJsonPath('error', 'Validation failed.')->assertJsonStructure(['errors' => [$field]]);
    expect(DB::table('channels')->count())->toBe(3);
})->with([
    'sem link' => [['title' => 'x', 'category' => 'c'], 'link'],
    'sem título' => [['link' => 'https://x.test', 'category' => 'c'], 'title'],
    'sem categoria' => [['link' => 'https://x.test', 'title' => 'x'], 'category'],
    'tags que não é lista' => [['link' => 'https://x.test', 'title' => 'x', 'category' => 'c', 'tags' => 'js'], 'tags'],
    'tag de 101 caracteres' => [['link' => 'https://x.test', 'title' => 'x', 'category' => 'c', 'tags' => [str_repeat('t', 101)]], 'tags.0'],
    'título de 256 caracteres' => [['link' => 'https://x.test', 'title' => str_repeat('t', 256), 'category' => 'c'], 'title'],
    'userGithub com caminho' => [['link' => 'https://x.test', 'title' => 'x', 'category' => 'c', 'userGithub' => '../x'], 'userGithub'],
    'link que não é texto' => [['link' => ['a'], 'title' => 'x', 'category' => 'c'], 'link'],
]);

it('userGithub vazio vira null e não chama o GitHub', function (): void {
    fakeGithubUsers();

    $this->postJson('/v1/channels', newChannel(['userGithub' => '']), as_admin('dev05'))->assertCreated()->assertJsonPath('userGithub', null);

    Http::assertNothingSent();
});
