<?php

declare(strict_types=1);

namespace App\Features\Channel\Exceptions;

use App\Shared\Exceptions\ApiException;

/** Alvo de follow ou ignore inexistente ou apagado: 400 por contrato (`erros-v1.md`). */
final class ChannelNotFound extends ApiException
{
    public function status(): int
    {
        return 400;
    }

    public function body(): array
    {
        return ['error' => 'Channel not exists'];
    }
}
