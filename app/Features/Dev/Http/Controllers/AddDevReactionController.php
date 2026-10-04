<?php

declare(strict_types=1);

namespace App\Features\Dev\Http\Controllers;

use App\Features\Dev\Actions\ReactToDev;
use App\Features\Dev\Http\Resources\DevResource;
use App\Shared\Auth\AuthenticatedDev;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** O tipo (`like` ou `dislike`) vem do `->defaults('type', ...)` da rota. */
final class AddDevReactionController
{
    public function __invoke(Request $request, ReactToDev $react, string $username, string $type): JsonResponse
    {
        return response()->json((new DevResource($react(AuthenticatedDev::of($request), $username, $type, true)))->resolve());
    }
}
