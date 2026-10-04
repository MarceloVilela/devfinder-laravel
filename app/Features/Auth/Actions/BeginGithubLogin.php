<?php

declare(strict_types=1);

namespace App\Features\Auth\Actions;

use App\Shared\Github\GithubClient;

/** Gera o `state` aleatório (F4-2) e a URL de autorização do GitHub. */
final class BeginGithubLogin
{
    public function __construct(private readonly GithubClient $github) {}

    /** @return array{state: string, url: string} */
    public function __invoke(): array
    {
        $state = bin2hex(random_bytes(32));

        return ['state' => $state, 'url' => $this->github->authorizeUrl($state)];
    }
}
