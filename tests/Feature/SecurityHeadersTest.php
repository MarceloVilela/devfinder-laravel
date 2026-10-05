<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('toda resposta leva nosniff e no-referrer, sem X-Powered-By', function (string $method, string $path, int $status): void {
    $response = $this->json($method, $path)->assertStatus($status);

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and($response->headers->has('X-Powered-By'))->toBeFalse();
})->with([
    'JSON de sucesso' => ['GET', '/v1/', 200],
    'listagem' => ['GET', '/v1/devs', 200],
    '404 do contrato' => ['GET', '/v1/nao-existe', 404],
    '401' => ['GET', '/v1/me', 401],
    '405' => ['DELETE', '/v1/devs', 405],
    '422' => ['GET', '/v1/search', 422],
    '429 não aplicável: health' => ['GET', '/health', 200],
]);

it('vale também no redirect do login e na UI do Swagger', function (): void {
    $login = $this->get('/v1/auth/github')->assertRedirect();
    $docs = $this->get('/docs')->assertOk();

    foreach ([$login, $docs] as $response) {
        expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
            ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer');
    }
});

it('não sobrescreve um Referrer-Policy já definido pela rota (o redirect do login) nem o Cache-Control', function (): void {
    $response = $this->get('/v1/auth/github/callback?code=x&state=y');

    expect($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('o ini do Lambda não anuncia o PHP nem guarda argumentos nos traces', function (): void {
    $ini = parse_ini_file(base_path('php/conf.d/devfinder.ini'));

    expect($ini)->toMatchArray(['expose_php' => '0', 'zend.exception_ignore_args' => '1']);
});
