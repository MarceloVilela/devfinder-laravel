<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Testing\TestResponse;
use Studio\Gesso\HttpMethod;

/** Confere o status e valida o corpo contra o OpenAPI (o `assertResponseMatchesOpenApiSchema` do Gesso é protegido). */
trait MatchesContract
{
    /** @param TestResponse<\Symfony\Component\HttpFoundation\Response> $response */
    public function matchesContract(TestResponse $response, HttpMethod $method, string $template, int $status): void
    {
        $response->assertStatus($status);
        $this->assertResponseMatchesOpenApiSchema($response, $method, $template);
    }
}
