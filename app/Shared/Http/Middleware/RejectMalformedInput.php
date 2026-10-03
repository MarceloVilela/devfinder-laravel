<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Exceptions\MalformedInput;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RejectMalformedInput
{
    public function handle(Request $request, Closure $next): Response
    {
        $values = [...$request->query->all(), ...($request->route()?->parameters() ?? [])];

        if (! $this->clean($values)) {
            throw new MalformedInput('malformed input');
        }

        return $next($request);
    }

    private function clean(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (! $this->clean($item)) {
                    return false;
                }
            }

            return true;
        }

        return ! is_string($value) || (! str_contains($value, "\0") && mb_check_encoding($value, 'UTF-8'));
    }
}
