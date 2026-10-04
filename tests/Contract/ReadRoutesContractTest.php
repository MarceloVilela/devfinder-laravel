<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;
use Studio\Gesso\HttpMethod;

// Cada rota pública de leitura contra o OpenAPI (ADR 0007), inclusive os corpos `null` (F3-2).

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

it('cumpre o contrato', function (string $uri, string $template): void {
    $response = $this->getJson($uri)->assertOk();

    $this->assertResponseMatchesOpenApiSchema($response, HttpMethod::GET, $template);
})->with([
    ['/v1/devs', '/devs'],
    ['/v1/devs?page=2', '/devs'],
    ['/v1/devs/dev01', '/devs/{username}'],
    ['/v1/devs/nobody', '/devs/{username}'],
    ['/v1/channels', '/channels'],
    ['/v1/channels/Canal%20Alpha', '/channels/{searchQuery}'],
    ['/v1/channels/nada', '/channels/{searchQuery}'],
    ['/v1/feed/trending', '/feed/trending'],
    ['/v1/feed/channel?channel_name=Canal%20Beta', '/feed/channel'],
    ['/v1/feed/channel?channel_name=nada', '/feed/channel'],
    ['/v1/video/vidalpha01', '/video/{idYoutubeWatch}'],
    ['/v1/video/nada', '/video/{idYoutubeWatch}'],
    ['/v1/search?q=Alpha', '/search'],
    ['/v1/search?q=zzz', '/search'],
]);

it('o validador reprova Dev sem o formato do contrato (prova de que o teste protege)', function (): void {
    $response = $this->getJson('/v1/devs/dev01');
    $bad = $response->json();
    $bad['_id'] = 1;

    $fake = Illuminate\Testing\TestResponse::fromBaseResponse(response()->json($bad));

    try {
        $this->assertResponseMatchesOpenApiSchema($fake, HttpMethod::GET, '/devs/{username}');
        $failed = false;
    } catch (PHPUnit\Framework\AssertionFailedError) {
        $failed = true;
    }

    expect($failed)->toBeTrue();
});

it('cumpre o contrato nas descrições em text/html', function (string $uri, string $template): void {
    $response = $this->get($uri)->assertOk();

    $this->assertResponseMatchesOpenApiSchema($response, HttpMethod::GET, $template);
})->with([
    ['/v1/description/feed', '/description/feed'],
    ['/v1/description/category', '/description/category'],
]);

it('GET /me cumpre o contrato (Dev)', function (): void {
    $token = app(App\Features\Auth\Support\TokenCodec::class)->issue('dev01');
    $response = $this->getJson('/v1/me', ['Authorization' => "Bearer {$token}"])->assertOk();

    $this->assertResponseMatchesOpenApiSchema($response, HttpMethod::GET, '/me');
});

it('GET /me sem token cumpre o contrato do 401', function (): void {
    $response = $this->getJson('/v1/me')->assertStatus(401);

    $this->assertResponseMatchesOpenApiSchema($response, HttpMethod::GET, '/me');
});

it('o 429 do login cumpre o contrato', function (): void {
    foreach (range(1, 10) as $_) {
        $this->get('/v1/auth/github');
    }
    $response = $this->getJson('/v1/auth/github')->assertStatus(429);

    $this->assertResponseMatchesOpenApiSchema($response, HttpMethod::GET, '/auth/github');
});
