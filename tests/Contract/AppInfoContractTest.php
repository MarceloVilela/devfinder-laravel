<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\AssertionFailedError;
use Studio\Gesso\HttpMethod;

// ADR 0007: a resposta real é validada contra `specs/fase-0-openapi.yaml` (fonte única, sem cópia).
// O prefixo `/v1` é removido pelo Gesso (`strip_prefixes`); a raiz precisa do path explícito `/`.

it('GET /v1 cumpre o schema AppInfo do contrato', function (): void {
    $response = $this->getJson('/v1')->assertOk();

    $this->assertResponseMatchesOpenApiSchema($response, HttpMethod::GET, '/');
});

it('o validador reprova resposta que diverge do contrato (prova de que o teste protege)', function (): void {
    // `_id` do Dev é string no contrato (D-7/D-10); aqui a rota devolve inteiro de propósito.
    Route::get('/v1/devs/{username}', fn(string $username): array => ['_id' => 1, 'user' => $username]);

    $response = $this->getJson('/v1/devs/dev01')->assertOk();

    try {
        $this->assertResponseMatchesOpenApiSchema($response);
        $failed = false;
    } catch (AssertionFailedError) {
        $failed = true;
    }

    expect($failed)->toBeTrue();
});

it('o validador reprova rota que não está no contrato', function (): void {
    Route::get('/v1/nao-documentada', fn(): array => ['a' => 1]);

    $response = $this->getJson('/v1/nao-documentada')->assertOk();

    try {
        $this->assertResponseMatchesOpenApiSchema($response);
        $failed = false;
    } catch (AssertionFailedError) {
        $failed = true;
    }

    expect($failed)->toBeTrue();
});
