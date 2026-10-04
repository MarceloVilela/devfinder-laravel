<?php

declare(strict_types=1);

namespace App\Features\Video\Http\Controllers;

use App\Features\Video\Actions\IngestVideos;
use App\Features\Video\Http\Requests\IngestVideosRequest;
use Illuminate\Http\JsonResponse;

final class IngestVideosController
{
    public function __invoke(IngestVideosRequest $request, IngestVideos $ingest): JsonResponse
    {
        return response()->json($ingest($request->candidates())->toContract());
    }
}
