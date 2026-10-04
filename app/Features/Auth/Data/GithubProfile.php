<?php

declare(strict_types=1);

namespace App\Features\Auth\Data;

final readonly class GithubProfile
{
    public function __construct(
        public string $login,
        public string $name,
        public string $bio,
        public string $avatar,
    ) {}
}
