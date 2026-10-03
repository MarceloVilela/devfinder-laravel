<?php

declare(strict_types=1);

namespace App\Features\Video\Http\Controllers;

use App\Features\Video\Data\VideoView;
use App\Features\Video\Http\Requests\ChannelFeedRequest;
use App\Features\Video\Http\Resources\VideoResource;
use App\Features\Video\Queries\VideoQueries;
use Illuminate\Http\JsonResponse;

final class ListChannelFeedController
{
    public function __construct(private readonly VideoQueries $videos) {}

    public function __invoke(ChannelFeedRequest $request): JsonResponse
    {
        return response()->json($this->videos->byChannel($request->channelName(), $request->pageNumber())->envelope(
            static fn(VideoView $video): array => (new VideoResource($video))->resolve(),
        ));
    }
}
