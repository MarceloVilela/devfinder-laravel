<?php

declare(strict_types=1);

namespace App\Features\Docs\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Serve o contrato-fonte (`specs/fase-0-openapi.yaml`) sem cópia: o YAML é a única fonte (ADR 0007). */
final class ShowOpenApiController
{
    public function __invoke(): BinaryFileResponse
    {
        return response()->file(
            base_path('specs/fase-0-openapi.yaml'),
            ['Content-Type' => 'application/yaml'],
        );
    }
}
