<?php

declare(strict_types=1);

namespace App\Features\Channel\Http\Controllers;

use App\Features\Channel\Actions\ReactToChannel;
use App\Shared\Auth\AuthenticatedDev;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** O tipo (`follow` ou `ignore`) vem do `->defaults('type', ...)` da rota; `{username}` é o nome do canal (contrato). */
final class AddChannelReactionController
{
    public function __invoke(Request $request, ReactToChannel $react, string $username, string $type): JsonResponse
    {
        return response()->json($react(AuthenticatedDev::of($request), $username, $type, true)->toContract());
    }
}
