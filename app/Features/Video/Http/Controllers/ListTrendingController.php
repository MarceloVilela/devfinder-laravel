<?php

declare(strict_types=1);

namespace App\Features\Video\Http\Controllers;

use App\Features\Video\Data\VideoView;
use App\Features\Video\Http\Resources\VideoResource;
use App\Features\Video\Queries\VideoQueries;
use App\Features\Video\Http\Requests\TrendingRequest;
use Illuminate\Http\JsonResponse;

final class ListTrendingController
{
    public function __construct(private readonly VideoQueries $videos) {}

    public function __invoke(TrendingRequest $request): JsonResponse
    {
        return response()->json($this->videos->trending($request->pageNumber(), $request->actor()?->id, $request->username())->envelope(
            static fn(VideoView $video): array => (new VideoResource($video))->resolve(),
        ));
    }
}
