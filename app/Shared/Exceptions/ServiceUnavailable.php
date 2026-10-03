<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/** Infraestrutura fora (banco, dependência externa): 503, com a causa só no log. */
class ServiceUnavailable extends ApiException
{
    public function status(): int
    {
        return 503;
    }

    public function body(): array
    {
        return ['error' => 'Service unavailable.'];
    }
}
