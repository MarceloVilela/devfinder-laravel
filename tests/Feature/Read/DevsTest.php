<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

it('lista a página 1 com 30 dos 35 devs, do mais recente ao mais antigo', function (): void {
    $json = $this->getJson('/v1/devs?page=1')->assertOk()->json();

    expect($json['total'])->toBe(35)
        ->and($json['itemsPerPage'])->toBe(30)
        ->and($json['page'])->toBe(1)
        ->and($json['totalPages'])->toBe(2)
        ->and($json['docs'])->toHaveCount(30)
        ->and($json['docs'][0]['user'])->toBe('dev35')
        ->and($json['docs'][29]['user'])->toBe('dev06');
});

it('lista a página 2 com os 5 restantes', function (): void {
    $json = $this->getJson('/v1/devs?page=2')->assertOk()->json();

    expect($json['docs'])->toHaveCount(5)
        ->and(array_column($json['docs'], 'user'))->toBe(['dev05', 'dev04', 'dev03', 'dev02', 'dev01']);
});

it('trata page ausente, zero, negativo, texto e decimal como 1', function (?string $page): void {
    $json = $this->getJson('/v1/devs' . ($page === null ? '' : "?page={$page}"))->assertOk()->json();

    expect($json['page'])->toBe(1)->and($json['docs'])->toHaveCount(30);
})->with([null, '0', '-1', 'abc', '1.5', '']);

it('serve a última página quando page passa do fim (P-3)', function (string $page): void {
    $json = $this->getJson("/v1/devs?page={$page}")->assertOk()->json();

    expect($json['page'])->toBe(2)->and($json['docs'])->toHaveCount(5);
})->with(['3', '999', '99999999999999999999999']);

it('ignora page em formato de lista e nome de coluna no lugar do número', function (string $query): void {
    $this->getJson("/v1/devs?{$query}")->assertOk()->assertJsonPath('page', 1);
})->with(['page[]=2', 'page=created_at', 'page=1;drop table devs', 'sort=name']);

it('ignora o Authorization (a personalização é da Fase 4, F3-1)', function (): void {
    $this->getJson('/v1/devs', ['Authorization' => 'Bearer lixo'])->assertOk()->assertJsonPath('total', 35);
});

it('devolve as reações de dev01 como ids e o resto vazio', function (): void {
    $dev01 = $this->getJson('/v1/devs/dev01')->assertOk()->json();
    $idOf = function (string $table, string $column, string $value): string {
        $id = DB::table($table)->where($column, $value)->value('id');

        return is_string($id) ? $id : '';
    };
    $id = fn(string $user): string => $idOf('devs', 'username', $user);
    $alpha = $idOf('channels', 'name', 'Canal Alpha');
    $beta = $idOf('channels', 'name', 'Canal Beta');

    expect($dev01['_id'])->toBe($id('dev01'))
        ->and($dev01['likes'])->toBe([$id('dev02')])
        ->and($dev01['deslikes'])->toBe([$id('dev03')])
        ->and($dev01['follow'])->toBe([$alpha])
        ->and($dev01['ignore'])->toBe([$beta])
        ->and($dev01['createdAt'])->toBe('2026-01-01T00:01:00+00:00');

    $dev02 = $this->getJson('/v1/devs/dev02')->json();
    expect($dev02['likes'])->toBe([])->and($dev02['follow'])->toBe([]);
});

it('devolve bio vazia quando nula (F3-9)', function (): void {
    $this->getJson('/v1/devs/dev03')->assertOk()->assertJsonPath('bio', '');
});

it('acha o dev sem caixa e sem acento', function (): void {
    $this->getJson('/v1/devs/DEV01')->assertOk()->assertJsonPath('user', 'dev01');
});

it('devolve 200 com null quando o dev não existe (F3-2)', function (): void {
    $response = $this->get('/v1/devs/nobody', ['Accept' => 'application/json'])->assertOk();

    expect($response->getContent())->toBe('null');
});

it('não lista dev apagado nem reação que aponta para ele (F3-8)', function (): void {
    DB::table('devs')->where('username', 'dev02')->update(['deleted_at' => now()]);

    $this->getJson('/v1/devs?page=1')->assertJsonPath('total', 34);
    $this->getJson('/v1/devs/dev02')->assertOk();
    expect($this->get('/v1/devs/dev02')->getContent())->toBe('null');
    $this->getJson('/v1/devs/dev01')->assertJsonPath('likes', []);
});

it('mostra a data em UTC mesmo com a sessão do banco em outro fuso (F3-10)', function (): void {
    DB::statement("set time zone 'America/Sao_Paulo'");

    $this->getJson('/v1/devs/dev01')->assertJsonPath('createdAt', '2026-01-01T00:01:00+00:00');
});
