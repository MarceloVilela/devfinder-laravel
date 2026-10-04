<?php

declare(strict_types=1);

namespace App\Features\Auth\Exceptions;

use App\Shared\Exceptions\ApiException;

/** GitHub fora do ar, lento ou com resposta inesperada (D-12): 502, causa só no log. */
final class GithubUnavailable extends ApiException
{
    public function status(): int
    {
        return 502;
    }

    public function body(): array
    {
        return ['error' => 'GitHub unavailable.'];
    }
}
