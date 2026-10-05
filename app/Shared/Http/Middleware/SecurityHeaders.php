<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos básicos em toda resposta (Fase 7, OWASP API8): `nosniff` e `no-referrer`, e sem `X-Powered-By` (não revela a versão do PHP).
 * O `php/conf.d/devfinder.ini` faz o mesmo no nível do PHP no Lambda; aqui o `header_remove` vale também para o servidor embutido e o FPM.
 * `Referrer-Policy` e `Cache-Control` já definidos por uma rota (ex.: o redirect do login) são preservados.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');

        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'no-referrer');
        }

        $response->headers->remove('X-Powered-By');

        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
