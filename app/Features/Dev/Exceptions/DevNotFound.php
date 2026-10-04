<?php

declare(strict_types=1);

namespace App\Features\Dev\Exceptions;

use App\Shared\Exceptions\ApiException;

/** Alvo de like ou dislike inexistente ou apagado: 400 por contrato (`erros-v1.md`). */
final class DevNotFound extends ApiException
{
    public function status(): int
    {
        return 400;
    }

    public function body(): array
    {
        return ['error' => 'Dev not exists'];
    }
}
