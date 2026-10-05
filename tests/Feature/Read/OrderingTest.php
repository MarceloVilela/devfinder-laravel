<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// Fase 7 (achado pelo G3 com dados reais): a collation padrão do banco muda entre o Neon ("C") e o Docker/CI (en_US), e o que não é ordenado
// de forma explícita muda de ordem entre ambientes. Estes testes passam em qualquer collation porque a ordem é fixada na consulta.

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

/** @param list<string> $tags */
function tagChannel(string $name, array $tags, string $category = 'Teste', string $link = 'https://x.test/o'): void
{
    $id = DB::table('channels')->insertGetId(['name' => $name, 'link' => $link, 'category' => $category, 'created_at' => now(), 'updated_at' => now()]);
    foreach ($tags as $tag) {
        DB::table('tags')->insertOrIgnore(['name' => $tag]);
        DB::table('channel_tag')->insert(['channel_id' => $id, 'tag_id' => DB::table('tags')->where('name', $tag)->value('id')]);
    }
}

it('as tags do canal saem sem caixa e sem acento, em collation "C", e o empate pela grafia em "C"', function (): void {
    tagChannel('Canal Tags', ['UX', 'freelancer', 'Mobile', 'mobile', 'arduino', 'Ação', 'acao']);

    $this->getJson('/v1/channels/Canal%20Tags')->assertOk()->assertJsonPath('tags', ['Ação', 'acao', 'arduino', 'freelancer', 'Mobile', 'mobile', 'UX']);
});

it('as hashtags das descrições seguem a mesma ordem das tags', function (): void {
    tagChannel('Canal Hash', ['UX', 'freelancer', 'Mobile', 'mobile'], 'Categoria Hash', 'https://x.test/hash');

    $body = (string) $this->get('/v1/description/category')->getContent();

    expect($body)->toContain('#freelancer<br />#Mobile<br />#mobile<br />#UX');
});

it('canais e categorias saem em ordem sem caixa e sem acento, em collation "C"', function (): void {
    tagChannel('zeta canal', [], 'Beta', 'https://x.test/1');
    tagChannel('Ágata canal', [], 'alfa', 'https://x.test/2');
    tagChannel('Beta canal', [], 'Alfa', 'https://x.test/3');

    $names = array_column($this->getJson('/v1/channels')->json(), 'name');

    expect(array_values(array_filter($names, fn(string $n): bool => str_ends_with($n, ' canal'))))->toBe(['Ágata canal', 'Beta canal', 'zeta canal']);
    expect((string) $this->get('/v1/description/category')->getContent())->toMatch('/sobre alfa.*sobre Alfa|sobre Alfa.*sobre alfa/s');
});
