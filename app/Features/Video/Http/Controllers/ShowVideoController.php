<?php

declare(strict_types=1);

namespace App\Features\Video\Http\Controllers;

use App\Features\Video\Http\Resources\VideoResource;
use App\Features\Video\Queries\VideoQueries;
use App\Shared\Http\JsonNull;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

final class ShowVideoController
{
    public function __construct(private readonly VideoQueries $videos) {}

    /** Vídeo inexistente: 200 com `null` (F3-2). */
    public function __invoke(string $idYoutubeWatch): JsonResponse|Response
    {
        $video = $this->videos->byYoutubeId($idYoutubeWatch);

        return $video === null ? JsonNull::response() : response()->json((new VideoResource($video))->resolve());
    }
}
