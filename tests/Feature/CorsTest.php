<?php

declare(strict_types=1);

// D-4: origem explícita, nunca `*`, sem credenciais.

beforeEach(function (): void {
    config(['cors.allowed_origins' => ['https://app.example.test']]);
});

it('libera só a origem configurada', function (): void {
    $response = $this->getJson('/v1', ['Origin' => 'https://app.example.test']);

    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('https://app.example.test');
    expect($response->headers->get('Access-Control-Allow-Credentials'))->toBeNull();
});

it('nunca ecoa uma origem não listada', function (): void {
    // Com uma origem só, o Laravel devolve sempre a configurada; o navegador rejeita se não for a dele.
    $response = $this->getJson('/v1', ['Origin' => 'https://evil.example.test']);

    expect($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://evil.example.test');
});

it('responde o preflight com os métodos do contrato e sem curinga', function (): void {
    $response = $this->call('OPTIONS', '/v1/devs', server: [
        'HTTP_ORIGIN' => 'https://app.example.test',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization',
    ]);

    expect($response->getStatusCode())->toBe(204);
    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('https://app.example.test');
    expect(str_contains((string) $response->headers->get('Access-Control-Allow-Origin'), '*'))->toBeFalse();
});

it('sem CORS_ALLOWED_ORIGINS nenhuma origem é liberada', function (): void {
    config(['cors.allowed_origins' => []]);

    $response = $this->getJson('/v1', ['Origin' => 'https://app.example.test']);

    expect($response->headers->get('Access-Control-Allow-Origin'))->toBeNull();
});

it('a configuração entregue nunca contém curinga', function (): void {
    expect(config('cors.allowed_origins'))->not->toContain('*');
});
