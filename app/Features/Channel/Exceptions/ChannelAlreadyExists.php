<?php

declare(strict_types=1);

namespace App\Features\Channel\Exceptions;

use App\Shared\Exceptions\ApiException;

/** O novo nome ou link colide com outro canal (rede de segurança do `UNIQUE`, F5-14): 409, nunca 500. */
final class ChannelAlreadyExists extends ApiException
{
    public function status(): int
    {
        return 409;
    }

    public function body(): array
    {
        return ['error' => 'Channel already exists.'];
    }
}
