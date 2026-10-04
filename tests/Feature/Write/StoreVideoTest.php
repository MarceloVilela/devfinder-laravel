<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function newVideo(array $overrides = []): array
{
    return $overrides + [
        'title' => 'Video de teste Fase 5',
        'url' => 'https://www.youtube.com/watch?v=zzzTESTID001',
        'channel' => 'Canal Alpha',
        'channel_url' => 'https://youtube.com/alpha',
    ];
}

it('cria o vídeo (201) com a thumbnail padrão do YouTube e o canal resolvido pelo JOIN', function (): void {
    $json = $this->postJson('/v1/video', newVideo(), as_admin('dev05'))->assertCreated()->json();

    expect($json['title'])->toBe('Video de teste Fase 5')
        ->and($json['url'])->toBe('https://www.youtube.com/watch?v=zzzTESTID001')
        ->and($json['thumbnail'])->toBe('https://i.ytimg.com/vi/zzzTESTID001/hqdefault.jpg')
        ->and($json['channel'])->toBe('Canal Alpha')
        ->and($json['channel_url'])->toBe('https://youtube.com/alpha')
        ->and($json['viewnum'])->toBeNull()
        ->and($json['date'])->toBeNull();

    $this->getJson('/v1/video/zzzTESTID001')->assertOk()->assertJsonPath('_id', $json['_id']);
    $this->getJson('/v1/feed/trending')->assertJsonPath('total', 56)->assertJsonPath('docs.0.title', 'Video de teste Fase 5');
});

it('mantém a thumbnail enviada e tira o &pp= da url e da thumbnail', function (): void {
    $json = $this->postJson('/v1/video', newVideo([
        'url' => 'https://www.youtube.com/watch?v=abcDEF12345&pp=ygUKcmFuZG9tdGFn',
        'thumbnail' => 'https://i.ytimg.com/vi/abcDEF12345/hq720.jpg?x=1&pp=zzz',
    ]), as_admin('dev05'))->assertCreated()->json();

    expect($json['url'])->toBe('https://www.youtube.com/watch?v=abcDEF12345')
        ->and($json['thumbnail'])->toBe('https://i.ytimg.com/vi/abcDEF12345/hq720.jpg?x=1');
});

it('acha o canal por nome, por link e por link alternativo (critérios de igualdade diferentes)', function (): void {
    DB::table('channels')->where('name', 'Canal Beta')->update(['alternative_link' => 'https://youtube.com/c/beta']);

    $porNome = $this->postJson('/v1/video', newVideo(['url' => 'https://www.youtube.com/watch?v=pornome0001', 'channel' => 'Canal Beta', 'channel_url' => 'https://nada.test']), as_admin('dev05'))->assertCreated();
    $porLink = $this->postJson('/v1/video', newVideo(['url' => 'https://www.youtube.com/watch?v=porlink0001', 'channel' => 'Outro Nome', 'channel_url' => 'https://youtube.com/beta']), as_admin('dev05'))->assertCreated();
    $porAlt = $this->postJson('/v1/video', newVideo(['url' => 'https://www.youtube.com/watch?v=poralt00001', 'channel' => 'Outro Nome', 'channel_url' => 'https://youtube.com/c/beta']), as_admin('dev05'))->assertCreated();

    expect([$porNome->json('channel'), $porLink->json('channel'), $porAlt->json('channel')])->toBe(['Canal Beta', 'Canal Beta', 'Canal Beta']);
});

it('canal desconhecido dá 400 com o corpo completo do contrato', function (): void {
    $this->postJson('/v1/video', newVideo([
        'title' => 'Video de teste', 'url' => 'https://www.youtube.com/watch?v=zzzTESTID001&pp=abc',
        'channel' => 'Canal Que Nao Existe', 'channel_url' => 'https://www.youtube.com/channel/UCNAOEXISTE',
    ]), as_admin('dev05'))->assertStatus(400)->assertExactJson([
        'errorMessage' => 'channel(Canal Que Nao Existe) not found, for: Video de teste',
        'title' => 'Video de teste',
        'url' => 'https://www.youtube.com/watch?v=zzzTESTID001',
        'channel' => 'Canal Que Nao Existe',
        'channel_url' => 'https://www.youtube.com/channel/UCNAOEXISTE',
        'thumbnail' => '',
    ]);

    expect(DB::table('videos')->count())->toBe(55);
});

it('canal apagado conta como desconhecido', function (): void {
    DB::table('channels')->where('name', 'Canal Alpha')->update(['deleted_at' => now()]);

    $this->postJson('/v1/video', newVideo(), as_admin('dev05'))->assertStatus(400)->assertJsonStructure(['errorMessage']);
});

it('o mesmo url de novo dá 409 com o errorMessage e o vídeo existente por inteiro', function (): void {
    $first = $this->postJson('/v1/video', newVideo(), as_admin('dev05'))->assertCreated()->json();

    $dup = $this->postJson('/v1/video', newVideo(['title' => 'Video de teste Fase 5 duplicado']), as_admin('dev05'))->assertStatus(409)->json();

    expect($dup['errorMessage'])->toBe('video(Video de teste Fase 5 duplicado) already exists')
        ->and($dup['_id'])->toBe($first['_id'])
        ->and($dup['title'])->toBe('Video de teste Fase 5')
        ->and($dup['channel'])->toBe('Canal Alpha');
    expect(DB::table('videos')->count())->toBe(56);
});

it('outro url com o mesmo id do YouTube também dá 409 (UNIQUE como rede de segurança), nunca 500', function (): void {
    $this->postJson('/v1/video', newVideo(), as_admin('dev05'))->assertCreated();

    $this->postJson('/v1/video', newVideo(['url' => 'https://www.youtube.com/watch?v=zzzTESTID001&list=PL1']), as_admin('dev05'))
        ->assertStatus(409)->assertJsonPath('title', 'Video de teste Fase 5');
    expect(DB::table('videos')->count())->toBe(56);
});

it('url duplicado de vídeo apagado libera a chave (índice parcial da Fase 1)', function (): void {
    DB::table('videos')->where('youtube_id', 'vidalpha01')->update(['deleted_at' => now()]);

    $this->postJson('/v1/video', newVideo(['url' => 'https://www.youtube.com/watch?v=vidalpha01']), as_admin('dev05'))->assertCreated();
});

it('422 para o que o v1 deixava quebrar: sem campo, sem v=, id inválido ou longo demais (D-3)', function (array $body, string $field): void {
    $this->postJson('/v1/video', $body, as_admin('dev05'))
        ->assertStatus(422)->assertJsonPath('error', 'Validation failed.')->assertJsonStructure(['errors' => [$field]]);
    expect(DB::table('videos')->count())->toBe(55);
})->with([
    'sem título' => [['url' => 'https://www.youtube.com/watch?v=abc', 'channel' => 'c', 'channel_url' => 'u'], 'title'],
    'sem url' => [['title' => 't', 'channel' => 'c', 'channel_url' => 'u'], 'url'],
    'sem canal' => [['title' => 't', 'url' => 'https://www.youtube.com/watch?v=abc', 'channel_url' => 'u'], 'channel'],
    'sem channel_url' => [['title' => 't', 'url' => 'https://www.youtube.com/watch?v=abc', 'channel' => 'c'], 'channel_url'],
    'url sem v=' => [['title' => 't', 'url' => 'https://youtu.be/abc', 'channel' => 'c', 'channel_url' => 'u'], 'url'],
    'id com 21 caracteres' => [['title' => 't', 'url' => 'https://www.youtube.com/watch?v=' . str_repeat('a', 21), 'channel' => 'c', 'channel_url' => 'u'], 'url'],
    'id com caractere inválido' => [['title' => 't', 'url' => 'https://www.youtube.com/watch?v=a%20b', 'channel' => 'c', 'channel_url' => 'u'], 'url'],
    'url de 501 caracteres' => [['title' => 't', 'url' => 'https://www.youtube.com/watch?v=abc&x=' . str_repeat('a', 480), 'channel' => 'c', 'channel_url' => 'u'], 'url'],
    'thumbnail que não é texto' => [['title' => 't', 'url' => 'https://www.youtube.com/watch?v=abc', 'channel' => 'c', 'channel_url' => 'u', 'thumbnail' => ['x']], 'thumbnail'],
]);

it('o parâmetro v= pode vir depois de outros parâmetros', function (): void {
    $json = $this->postJson('/v1/video', newVideo(['url' => 'https://www.youtube.com/watch?feature=share&v=idDepois001']), as_admin('dev05'))->assertCreated()->json();

    expect($json['thumbnail'])->toBe('https://i.ytimg.com/vi/idDepois001/hqdefault.jpg');
});
