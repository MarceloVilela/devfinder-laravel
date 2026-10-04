<?php

declare(strict_types=1);

namespace App\Features\Auth\Http\Controllers;

use App\Features\Auth\Actions\BeginGithubLogin;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Cookie;

final class RedirectToGithubController
{
    public function __invoke(BeginGithubLogin $begin): RedirectResponse
    {
        ['state' => $state, 'url' => $url] = $begin();

        /** @var array{name: string, path: string, ttl_minutes: int, secure: bool} $cookie */
        $cookie = config('devfinder.auth.state_cookie');

        return redirect()->away($url)
            ->withCookie(new Cookie(
                $cookie['name'],
                $state,
                now()->addMinutes($cookie['ttl_minutes']),
                $cookie['path'],
                null,
                $cookie['secure'],
                true,
                false,
                Cookie::SAMESITE_LAX,
            ))
            ->header('Cache-Control', 'no-store');
    }
}
