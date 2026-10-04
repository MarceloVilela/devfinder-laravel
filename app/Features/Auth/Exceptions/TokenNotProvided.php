<?php

declare(strict_types=1);

namespace App\Features\Auth\Exceptions;

use App\Shared\Exceptions\ApiException;

final class TokenNotProvided extends ApiException
{
    public function status(): int
    {
        return 401;
    }

    public function body(): array
    {
        return ['error' => 'Token not provided.'];
    }
}
