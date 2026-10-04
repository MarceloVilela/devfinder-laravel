<?php

declare(strict_types=1);

namespace App\Features\Dev\Exceptions;

use App\Shared\Exceptions\ApiException;

/** `POST /devs` com username que não existe no GitHub: 404, não 500 como no v1 (D-6). */
final class GithubUserNotFound extends ApiException
{
    public function status(): int
    {
        return 404;
    }

    public function body(): array
    {
        return ['error' => 'GitHub user not found.'];
    }
}
