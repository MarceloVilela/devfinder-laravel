<?php

declare(strict_types=1);

namespace App\Features\Auth\Actions;

use App\Shared\Exceptions\GithubUnavailable;
use App\Shared\Github\GithubClient;
use App\Features\Dev\Actions\EnsureDev;
use App\Features\Auth\Support\TokenCodec;

/** `state` conferido, `code` trocado, Dev criado ou reaproveitado e token emitido (F4-2 a F4-8). */
final class CompleteGithubLogin
{
    public function __construct(
        private readonly GithubClient $github,
        private readonly EnsureDev $devs,
        private readonly TokenCodec $tokens,
    ) {}

    /**
     * Token do login, ou `null` quando a falha é do usuário (sem `code`, `state` inválido, `code` recusado).
     *
     * @throws GithubUnavailable
     */
    public function __invoke(?string $code, ?string $state, ?string $cookieState): ?string
    {
        if ($code === null || $code === '' || ! $this->stateMatches($state, $cookieState)) {
            return null;
        }

        $profile = $this->github->profileFor($code);

        if ($profile === null) {
            return null;
        }

        return $this->tokens->issue(($this->devs)($profile));
    }

    private function stateMatches(?string $state, ?string $cookieState): bool
    {
        return $state !== null && $state !== '' && $cookieState !== null && hash_equals($cookieState, $state);
    }
}
