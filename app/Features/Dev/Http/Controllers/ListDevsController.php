<?php

declare(strict_types=1);

namespace App\Features\Dev\Http\Controllers;

use App\Features\Dev\Data\DevView;
use App\Features\Dev\Http\Resources\DevResource;
use App\Features\Dev\Queries\DevQueries;
use App\Shared\Http\Requests\PageRequest;
use Illuminate\Http\JsonResponse;

final class ListDevsController
{
    public function __construct(private readonly DevQueries $devs) {}

    public function __invoke(PageRequest $request): JsonResponse
    {
        $result = $this->devs->page($request->pageNumber(), $request->actor()?->id);

        return response()->json($result->envelope(
            static fn(DevView $dev): array => (new DevResource($dev))->resolve(),
        ));
    }
}
