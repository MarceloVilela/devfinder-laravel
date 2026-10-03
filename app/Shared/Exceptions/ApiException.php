<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use RuntimeException;
use Throwable;

/** Erro de domínio ou de infraestrutura com status e corpo públicos definidos (ADR 0008). */
abstract class ApiException extends RuntimeException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    abstract public function status(): int;

    /** @return array<string, mixed> */
    abstract public function body(): array;
}
