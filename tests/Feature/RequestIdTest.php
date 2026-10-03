<?php

declare(strict_types=1);

it('gera X-Request-Id quando o cliente não manda', function (): void {
    $id = $this->getJson('/health')->headers->get('X-Request-Id');

    expect($id)->toBeString()->toMatch('/^[0-9a-f-]{36}$/');
});

it('reaproveita um id seguro enviado pelo cliente', function (): void {
    $this->getJson('/health', ['X-Request-Id' => 'abc-12345678'])->assertHeader('X-Request-Id', 'abc-12345678');
});

it('descarta id com caracteres perigosos (injeção em log)', function (): void {
    $id = $this->getJson('/health', ['X-Request-Id' => "x\nforged=1"])->headers->get('X-Request-Id');

    expect(str_contains((string) $id, 'forged'))->toBeFalse();
});

it('o erro também leva o cabeçalho', function (): void {
    $this->getJson('/nao-existe')->assertHeader('X-Request-Id');
});
