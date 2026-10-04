<?php

declare(strict_types=1);

namespace App\Features\Auth\Support;

use Illuminate\Http\Request;

/** De onde vem o token da requisição: o cookie de sessão primeiro, depois `Authorization: Bearer` (mesma ordem do original). Nunca a query string. */
final class TokenSource
{
    public function __construct(private readonly SessionCookie $cookie) {}

    /**
     * Resultado de `read()`: `null` = nenhum token apresentado; `''` = apresentado em formato inválido; senão o token.
     */
    public function read(Request $request): ?string
    {
        $session = $request->cookies->get($this->cookie->name());

        if (is_string($session) && $session !== '') {
            return $session;
        }

        $header = $request->headers->get('Authorization');

        if ($header === null || trim($header) === '') {
            return null;
        }

        return preg_match('/^Bearer +(\S+)$/i', trim($header), $matches) === 1 ? $matches[1] : '';
    }
}
