<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

it('lista os 3 canais por nome, sem envelope, com tags', function (): void {
    $json = $this->getJson('/v1/channels')->assertOk()->json();

    expect(array_column($json, 'name'))->toBe(['Canal Alpha', 'Canal Beta', 'Canal Zeta'])
        ->and($json[0]['tags'])->toBe(['javascript', 'testes'])
        ->and($json[1]['tags'])->toBe(['react'])
        ->and($json[2]['tags'])->toBe([])
        ->and($json[0]['likes'])->toBe([])
        ->and($json[0]['deslikes'])->toBe([])
        ->and($json[0]['userGithub'])->toBeNull()
        ->and($json[0]['createdAt'])->toBe('2026-01-01T00:00:00+00:00');
});

it('acha o canal por nome, sem caixa e sem acento', function (string $term): void {
    $this->getJson('/v1/channels/' . rawurlencode($term))->assertOk()->assertJsonPath('name', 'Canal Alpha');
})->with(['Canal Alpha', 'canal alpha', 'CANAL ÁLPHA']);

it('acha o canal pelo link, inclusive com barras no segmento (F3-5)', function (string $path): void {
    $this->getJson("/v1/channels/{$path}")->assertOk()->assertJsonPath('name', 'Canal Alpha');
})->with([
    'link codificado' => 'https%3A%2F%2Fyoutube.com%2Falpha',
    'link com barras' => 'https://youtube.com/alpha',
]);

it('acha o canal pelo link alternativo', function (): void {
    DB::table('channels')->where('name', 'Canal Alpha')->update(['alternative_link' => 'https://youtube.com/c/alpha']);

    $this->getJson('/v1/channels/' . rawurlencode('https://youtube.com/c/alpha'))->assertOk()->assertJsonPath('name', 'Canal Alpha');
});

it('devolve 200 com null quando o canal não existe (F3-2)', function (): void {
    expect($this->get('/v1/channels/nao-existe', ['Accept' => 'application/json'])->assertOk()->getContent())->toBe('null');
});

it('não lista canal apagado', function (): void {
    DB::table('channels')->where('name', 'Canal Zeta')->update(['deleted_at' => now()]);

    expect($this->getJson('/v1/channels')->json())->toHaveCount(2);
    expect($this->get('/v1/channels/Canal%20Zeta')->getContent())->toBe('null');
});

it('não lista tag apagada', function (): void {
    DB::table('tags')->where('name', 'testes')->update(['deleted_at' => now()]);

    $this->getJson('/v1/channels/Canal%20Alpha')->assertJsonPath('tags', ['javascript']);
});
