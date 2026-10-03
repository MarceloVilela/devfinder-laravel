<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/** Byte nulo ou UTF-8 inválido na entrada: o PostgreSQL os rejeita, então nem chegam ao banco (F3-6). */
final class MalformedInput extends ApiException
{
    public function status(): int
    {
        return 400;
    }

    public function body(): array
    {
        return ['error' => 'Malformed input.'];
    }
}
