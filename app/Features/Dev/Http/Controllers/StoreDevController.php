<?php

declare(strict_types=1);

namespace App\Features\Dev\Http\Controllers;

use App\Features\Dev\Actions\StoreDev;
use App\Features\Dev\Http\Requests\StoreDevRequest;
use App\Features\Dev\Http\Resources\DevResource;
use Illuminate\Http\JsonResponse;

final class StoreDevController
{
    /** Sempre 201, mesmo se o dev já existia (paridade com o v1 e o original). */
    public function __invoke(StoreDevRequest $request, StoreDev $store): JsonResponse
    {
        return response()->json((new DevResource($store($request->username())))->resolve(), 201);
    }
}
