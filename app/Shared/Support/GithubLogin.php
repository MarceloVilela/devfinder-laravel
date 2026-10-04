<?php

declare(strict_types=1);

namespace App\Shared\Support;

/** Formato do login do GitHub. O valor entra na URL da API do GitHub, então `/`, `..`, espaço e `?` nunca podem passar (F5-7). */
final class GithubLogin
{
    public const PATTERN = '/^[A-Za-z0-9](?:[A-Za-z0-9-]{0,38})$/';
}
