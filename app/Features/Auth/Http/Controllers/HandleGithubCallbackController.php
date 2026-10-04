<?php

declare(strict_types=1);

namespace App\Features\Auth\Http\Controllers;

use App\Features\Auth\Actions\CompleteGithubLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

final class HandleGithubCallbackController
{
    public function __invoke(Request $request, CompleteGithubLogin $login): RedirectResponse
    {
        /** @var array{name: string, path: string, secure: bool} $cookie */
        $cookie = config('devfinder.auth.state_cookie');
        $webUrl = config('devfinder.auth.web_url');
        $webUrl = is_string($webUrl) ? $webUrl : '';

        $token = $login(
            $this->text($request->query('code')),
            $this->text($request->query('state')),
            $this->text($request->cookies->get($cookie['name'])),
        );

        // Falha do usuário volta ao front sem token (F4-3). O token só viaja neste redirect (F4-5).
        $response = redirect()->away($token === null ? "{$webUrl}/login" : "{$webUrl}/login?token=" . urlencode($token));

        return $response
            ->withCookie(new Cookie($cookie['name'], '', 1, $cookie['path'], null, $cookie['secure'], true, false, Cookie::SAMESITE_LAX))
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
