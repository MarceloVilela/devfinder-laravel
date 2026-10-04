<?php

declare(strict_types=1);

namespace App\Features\Auth\Http\Controllers;

use App\Features\Auth\Support\SessionCookie;
use Illuminate\Http\Response;

final class LogoutController
{
    /** Limpa o cookie de sessão (só o backend consegue: é `httpOnly`). 204 mesmo sem sessão: sair é idempotente. */
    public function __invoke(SessionCookie $session): Response
    {
        return response()->noContent()->withCookie($session->forget())->header('Cache-Control', 'no-store');
    }
}
