<?php

declare(strict_types=1);

namespace App\Features\Auth\Exceptions;

use App\Shared\Exceptions\ApiException;

/** Token ausente de assinatura válida, expirado, adulterado ou de Dev que não existe (D-2). */
final class TokenInvalid extends ApiException
{
    public function status(): int
    {
        return 401;
    }

    public function body(): array
    {
        return ['error' => 'Token invalid.'];
    }
}
