<?php

declare(strict_types=1);

namespace App\Features\Dev\Http\Controllers;

use App\Features\Dev\Actions\ListReactedDevs;
use App\Features\Dev\Data\DevView;
use App\Features\Dev\Http\Resources\DevResource;
use App\Shared\Auth\AuthenticatedDev;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListReactedDevsController
{
    public function __invoke(Request $request, ListReactedDevs $list, string $type): JsonResponse
    {
        return response()->json(array_map(
            static fn(DevView $dev): array => (new DevResource($dev))->resolve(),
            $list(AuthenticatedDev::of($request), $type),
        ));
    }
}
