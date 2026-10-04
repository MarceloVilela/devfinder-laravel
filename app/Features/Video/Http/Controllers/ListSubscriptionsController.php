<?php

declare(strict_types=1);

namespace App\Features\Video\Http\Controllers;

use App\Features\Video\Data\VideoView;
use App\Features\Video\Http\Resources\VideoResource;
use App\Features\Video\Queries\VideoQueries;
use App\Shared\Auth\AuthenticatedDev;
use App\Shared\Http\Requests\PageRequest;
use Illuminate\Http\JsonResponse;

final class ListSubscriptionsController
{
    public function __construct(private readonly VideoQueries $videos) {}

    public function __invoke(PageRequest $request): JsonResponse
    {
        $dev = AuthenticatedDev::of($request);

        return response()->json($this->videos->subscribed($dev->id, $request->pageNumber())->envelope(
            static fn(VideoView $video): array => (new VideoResource($video))->resolve(),
        ));
    }
}
