<?php

declare(strict_types=1);

it('serve a UI do contrato em /docs', function (): void {
    $this->get('/docs')->assertOk()->assertSee('swagger-ui', false);
});

it('serve o YAML-fonte sem cópia', function (): void {
    $response = $this->get('/docs/openapi.yaml')->assertOk();

    expect(file_get_contents($response->baseResponse->getFile()->getPathname()))
        ->toBe(file_get_contents(base_path('specs/fase-0-openapi.yaml')));
});
