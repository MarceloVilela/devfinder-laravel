<?php

declare(strict_types=1);

namespace App\Shared\Github;

final readonly class GithubProfile
{
    public function __construct(
        public string $login,
        public string $name,
        public string $bio,
        public string $avatar,
    ) {}
}
