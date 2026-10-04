<?php

declare(strict_types=1);

namespace App\Features\Video\Exceptions;

use App\Shared\Exceptions\ApiException;

/**
 * O JSONBin não entregou os candidatos (fora do ar depois dos retries, credencial recusada ou formato inesperado). Só o comando agendado a
 * usa (exit 1 e erro no log); por ser `ApiException`, se um dia sair por HTTP já tem corpo público (502). Nunca carrega a chave.
 */
final class JsonBinUnavailable extends ApiException
{
    public function status(): int
    {
        return 502;
    }

    public function body(): array
    {
        return ['error' => 'JSONBin unavailable.'];
    }
}
