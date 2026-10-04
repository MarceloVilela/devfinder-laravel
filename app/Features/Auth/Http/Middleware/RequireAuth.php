<?php

declare(strict_types=1);

namespace App\Features\Auth\Http\Middleware;

use App\Features\Auth\Exceptions\TokenInvalid;
use App\Features\Auth\Exceptions\TokenNotProvided;
use App\Features\Auth\Queries\DevLookup;
use App\Features\Auth\Support\TokenCodec;
use App\Features\Auth\Support\TokenSource;
use App\Shared\Auth\AuthenticatedDev;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Rota autenticada: 401 sem token (`Token not provided.`) ou com token inválido (`Token invalid.`, D-2). Cookie de sessão ou Bearer. */
final class RequireAuth
{
    public function __construct(
        private readonly TokenCodec $tokens,
        private readonly TokenSource $source,
        private readonly DevLookup $devs,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->source->read($request);

        if ($token === null) {
            throw new TokenNotProvided('no token');
        }

        $username = $token === '' ? null : $this->tokens->username($token);
        $dev = $username === null ? null : $this->devs->byUsername($username);

        if ($dev === null) {
            throw new TokenInvalid('token rejected');
        }

        $request->attributes->set(AuthenticatedDev::ATTRIBUTE, $dev);

        return $next($request);
    }
}
