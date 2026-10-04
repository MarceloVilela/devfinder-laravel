<?php

declare(strict_types=1);

namespace App\Features\Auth\Http\Middleware;

use App\Features\Auth\Exceptions\TokenInvalid;
use App\Features\Auth\Exceptions\TokenNotProvided;
use App\Features\Auth\Queries\DevLookup;
use App\Features\Auth\Support\TokenCodec;
use App\Shared\Auth\AuthenticatedDev;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Rota autenticada: 401 sem token (`Token not provided.`) ou com token inválido (`Token invalid.`, D-2). */
final class RequireAuth
{
    public function __construct(
        private readonly TokenCodec $tokens,
        private readonly DevLookup $devs,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->headers->get('Authorization');

        if ($header === null || trim($header) === '') {
            throw new TokenNotProvided('no authorization header');
        }

        $dev = $this->resolve($header);

        if ($dev === null) {
            throw new TokenInvalid('token rejected');
        }

        $request->attributes->set(AuthenticatedDev::ATTRIBUTE, $dev);

        return $next($request);
    }

    private function resolve(string $header): ?AuthenticatedDev
    {
        if (preg_match('/^Bearer +(\S+)$/i', trim($header), $matches) !== 1) {
            return null;
        }

        $username = $this->tokens->username($matches[1]);

        return $username === null ? null : $this->devs->byUsername($username);
    }
}
