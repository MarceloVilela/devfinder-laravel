<?php

declare(strict_types=1);

namespace App\Features\Channel\Http\Controllers;

use App\Features\Channel\Actions\StoreChannel;
use App\Features\Channel\Http\Requests\StoreChannelRequest;
use App\Features\Channel\Http\Resources\ChannelResource;
use App\Features\Channel\Queries\ChannelQueries;
use Illuminate\Http\JsonResponse;

final class StoreChannelController
{
    public function __construct(private readonly ChannelQueries $channels) {}

    /** 201 canal novo, 200 canal atualizado (paridade com o original: o update responde sem status explícito). */
    public function __invoke(StoreChannelRequest $request, StoreChannel $store): JsonResponse
    {
        $stored = $store($request->channel());
        $channel = $this->channels->byId($stored->id);

        abort_if($channel === null, 500);

        return response()->json((new ChannelResource($channel))->resolve(), $stored->created ? 201 : 200);
    }
}
