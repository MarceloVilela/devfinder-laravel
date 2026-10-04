<?php

declare(strict_types=1);

namespace App\Features\Auth\Http\Middleware;

use App\Features\Auth\Exceptions\Forbidden;
use App\Shared\Auth\AuthenticatedDev;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Só `ADMIN` passa (F5-15). Roda depois do `auth` e antes do `throttle`: o papel vem do banco a cada requisição (1 query, a mesma do
 * `auth`), então promover ou rebaixar no banco vale já na requisição seguinte, com o mesmo token.
 */
final class RequireAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! AuthenticatedDev::of($request)->isAdmin()) {
            throw new Forbidden('admin role required');
        }

        return $next($request);
    }
}
