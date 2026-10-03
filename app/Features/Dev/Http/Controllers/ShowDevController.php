<?php

declare(strict_types=1);

namespace App\Features\Dev\Http\Controllers;

use App\Features\Dev\Http\Resources\DevResource;
use App\Features\Dev\Queries\DevQueries;
use App\Shared\Http\JsonNull;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

final class ShowDevController
{
    public function __construct(private readonly DevQueries $devs) {}

    /** Dev inexistente: 200 com `null` (F3-2). */
    public function __invoke(string $username): JsonResponse|Response
    {
        $dev = $this->devs->byUsername($username);

        return $dev === null ? JsonNull::response() : response()->json((new DevResource($dev))->resolve());
    }
}
