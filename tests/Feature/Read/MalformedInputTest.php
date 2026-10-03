<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

it('responde 400 a byte nulo ou UTF-8 inválido na query ou no segmento (F3-6)', function (string $path): void {
    $this->getJson($path)->assertStatus(400)->assertExactJson(['error' => 'Malformed input.']);
})->with([
    'q com nulo' => '/v1/search?q=a%00b',
    'q com utf-8 inválido' => '/v1/search?q=%ff%fe',
    'segmento com nulo' => '/v1/devs/a%00b',
    'feed com nulo' => '/v1/feed/channel?channel_name=a%00b',
    'vídeo com nulo' => '/v1/video/%00',
]);

it('responde 400 a segmento com UTF-8 inválido (o próprio Symfony recusa antes da aplicação)', function (): void {
    $this->getJson('/v1/channels/%c3%28')->assertStatus(400);
});

it('trata só o byte nulo como vazio (o TrimStrings do Laravel o remove)', function (): void {
    $this->getJson('/v1/search?q=%00')->assertStatus(422);
    $this->getJson('/v1/feed/channel?channel_name=%00')->assertOk()->assertJsonPath('total', 0);
});

it('aceita acentos e emoji válidos', function (): void {
    $this->getJson('/v1/devs/' . rawurlencode('çãõ😀'))->assertOk();
    $this->getJson('/v1/search?q=' . rawurlencode('ação 😀'))->assertOk()->assertExactJson([]);
});

it('não vaza stack trace nem SQL em nenhum caminho de erro', function (): void {
    $body = $this->getJson('/v1/search?q=%00')->getContent();

    expect($body)->not->toContain('SQLSTATE');
    expect($body)->not->toContain('select ');
    expect($body)->not->toContain('vendor/');
});
