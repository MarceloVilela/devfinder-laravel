<?php

declare(strict_types=1);

namespace App\Features\Auth\Exceptions;

use App\Shared\Exceptions\ApiException;

/** Token válido, mas o papel do dev não basta (F5-15): 403. */
final class Forbidden extends ApiException
{
    public function status(): int
    {
        return 403;
    }

    public function body(): array
    {
        return ['error' => 'Forbidden.'];
    }
}
