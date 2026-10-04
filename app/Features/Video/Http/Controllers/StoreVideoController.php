<?php

declare(strict_types=1);

namespace App\Features\Video\Http\Controllers;

use App\Features\Video\Actions\StoreVideo;
use App\Features\Video\Http\Requests\StoreVideoRequest;
use App\Features\Video\Http\Resources\VideoResource;
use Illuminate\Http\JsonResponse;

final class StoreVideoController
{
    public function __invoke(StoreVideoRequest $request, StoreVideo $store): JsonResponse
    {
        return response()->json((new VideoResource($store($request->video())))->resolve(), 201);
    }
}
