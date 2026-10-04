<?php

declare(strict_types=1);

namespace App\Shared\Auth;

use Carbon\CarbonImmutable;

/** Dev identificado pelo token da requisição (o middleware `auth` o carrega em 1 query, F4-7). */
final readonly class AuthenticatedDev
{
    public const ATTRIBUTE = 'auth.dev';

    public function __construct(
        public string $id,
        public string $username,
        public string $name,
        public string $bio,
        public string $avatar,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}
}
