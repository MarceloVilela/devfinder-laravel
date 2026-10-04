<?php

declare(strict_types=1);

namespace App\Features\Auth\Http\Middleware;

use App\Features\Auth\Queries\DevLookup;
use App\Features\Auth\Support\TokenCodec;
use App\Shared\Auth\AuthenticatedDev;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Rota com autenticação opcional: token válido personaliza; qualquer outra coisa segue anônima, nunca 401 (D-2). */
final class OptionalAuth
{
    public function __construct(
        private readonly TokenCodec $tokens,
        private readonly DevLookup $devs,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->headers->get('Authorization');

        if ($header !== null && preg_match('/^Bearer +(\S+)$/i', trim($header), $matches) === 1) {
            $username = $this->tokens->username($matches[1]);
            $dev = $username === null ? null : $this->devs->byUsername($username);

            if ($dev !== null) {
                $request->attributes->set(AuthenticatedDev::ATTRIBUTE, $dev);
            }
        }

        return $next($request);
    }
}
