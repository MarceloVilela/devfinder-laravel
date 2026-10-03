<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

it('404 em rota inexistente', function (): void {
    $this->getJson('/nao-existe')->assertNotFound()->assertExactJson(['error' => 'Not found.']);
});

it('404 também sem o cabeçalho Accept: json', function (): void {
    $this->get('/v1/nao-existe')->assertNotFound()->assertExactJson(['error' => 'Not found.']);
});

it('405 em método não permitido', function (): void {
    $this->postJson('/health')->assertStatus(405)->assertExactJson(['error' => 'Method not allowed.']);
});

it('422 com errors por campo', function (): void {
    Route::get('/_teste/422', fn() => throw ValidationException::withMessages(['title' => ['required']]));

    $this->getJson('/_teste/422')
        ->assertStatus(422)
        ->assertExactJson(['error' => 'Validation failed.', 'errors' => ['title' => ['required']]]);
});

it('500 sem stack trace nem mensagem interna com APP_DEBUG=false', function (): void {
    config(['app.debug' => false]);
    Route::get('/_teste/500', fn() => throw new RuntimeException('segredo interno'));

    $response = $this->getJson('/_teste/500')->assertStatus(500)->assertExactJson(['error' => 'Internal server error.']);

    $body = (string) $response->getContent();

    expect(str_contains($body, 'segredo interno'))->toBeFalse();
    expect(str_contains($body, 'trace'))->toBeFalse();
});
