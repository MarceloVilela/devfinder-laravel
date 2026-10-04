<?php

declare(strict_types=1);

namespace App\Shared\Github;

use App\Shared\Exceptions\GithubUnavailable;

/** Fronteira com o GitHub (A7): a aplicação só conhece esta interface; os testes a trocam por `Http::fake`. */
interface GithubClient
{
    /** URL da tela de autorização (sem `scope`, como o original: o perfil público não precisa). */
    public function authorizeUrl(string $state): string;

    /**
     * Perfil público de um login do GitHub, ou `null` se ele não existe (404).
     *
     * @throws GithubUnavailable GitHub fora do ar, lento ou com resposta inesperada
     */
    public function publicProfile(string $login): ?GithubProfile;

    /**
     * Perfil do dono do `code`, ou `null` se o GitHub recusou o `code` (usado, expirado, inválido).
     *
     * @throws GithubUnavailable GitHub fora do ar, lento ou com resposta inesperada
     */
    public function profileFor(string $code): ?GithubProfile;
}
