<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

it('acha canal e vídeos, canais primeiro, com value em encodeURI', function (): void {
    $json = $this->getJson('/v1/search?q=Alpha')->assertOk()->json();

    expect($json[0])->toBe(['value' => 'Canal%20Alpha', 'label' => 'Canal Alpha', 'type' => 'channel'])
        ->and($json)->toHaveCount(21)
        ->and($json[1]['type'])->toBe('video')
        ->and(array_count_values(array_column($json, 'type')))->toBe(['channel' => 1, 'video' => 20]);
});

it('limita a 10 canais e 20 vídeos', function (): void {
    DB::table('channels')->insert(array_map(static fn(int $n): array => [
        'name' => "Canal Extra {$n}", 'link' => "https://youtube.com/extra{$n}", 'category' => 'x',
        'created_at' => now(), 'updated_at' => now(),
    ], range(1, 15)));

    $json = $this->getJson('/v1/search?q=Beta')->assertOk()->json();
    $types = array_count_values(array_column($json, 'type'));

    expect($types)->toBe(['channel' => 1, 'video' => 20]);
    $extras = array_count_values(array_column($this->getJson('/v1/search?q=extra')->json(), 'type'));
    expect($extras)->toBe(['channel' => 10]);
});

it('casa sem caixa, sem acento e pelo link do canal', function (string $term, string $label): void {
    $labels = array_column($this->getJson('/v1/search?q=' . rawurlencode($term))->assertOk()->json(), 'label');

    expect($labels)->toContain($label);
})->with([['alpha', 'Canal Alpha'], ['VÍDEO BETA 01', 'Vídeo Beta 01'], ['youtube.com/zeta', 'Canal Zeta']]);

it('devolve lista vazia sem resultado', function (): void {
    $this->getJson('/v1/search?q=zzzz-nao-existe')->assertOk()->assertExactJson([]);
});

it('responde 422 sem q, com q vazio ou só espaços, ou com mais de 100 caracteres', function (string $query): void {
    $this->getJson("/v1/search{$query}")
        ->assertStatus(422)
        ->assertJsonPath('error', 'Validation failed.')
        ->assertJsonStructure(['errors' => ['q']]);
})->with(['', '?q=', '?q=%20%20', '?q[]=a', '?q=' . '' . 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']);

it('trata termos hostis como texto, sem erro e sem casar tudo', function (string $term): void {
    $started = microtime(true);
    $this->getJson('/v1/search?q=' . rawurlencode($term))->assertOk()->assertExactJson([]);

    expect(microtime(true) - $started)->toBeLessThan(1.0);
})->with([
    'aspa simples' => "'",
    'aspa dupla' => '"',
    'injeção' => "' or 1=1 --",
    'injeção com ponto e vírgula' => "'; drop table videos; --",
    'percent' => '%',
    'underscore' => '_',
    'barra invertida' => '\\',
    'percent e barra' => '\\%',
    'regex catastrófica' => '(a+)+$',
    'colchete' => '[a-',
    'coluna' => 'name; select',
]);

it('casa % e _ só quando literais no dado', function (): void {
    DB::table('videos')->where('youtube_id', 'vidalpha01')->update(['title' => '100%_certo']);

    expect(array_column($this->getJson('/v1/search?q=' . rawurlencode('100%_'))->json(), 'label'))->toBe(['100%_certo']);
    expect($this->getJson('/v1/search?q=' . rawurlencode('1%0'))->json())->toBe([]);
});

it('ignora o token (a resposta não muda, F3-1)', function (): void {
    $anon = $this->getJson('/v1/search?q=Alpha')->json();
    $auth = $this->getJson('/v1/search?q=Alpha', ['Authorization' => 'Bearer qualquer'])->json();

    expect($auth)->toBe($anon);
});

it('não lista canal nem vídeo apagado', function (): void {
    DB::table('channels')->where('name', 'Canal Alpha')->update(['deleted_at' => now()]);

    $json = $this->getJson('/v1/search?q=Alpha')->json();
    expect($json)->toBe([]);
});
