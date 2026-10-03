<?php

declare(strict_types=1);

namespace App\Features\Channel\Http\Controllers;

use App\Features\Channel\Http\Resources\ChannelResource;
use App\Features\Channel\Queries\ChannelQueries;
use App\Shared\Http\JsonNull;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

final class ShowChannelController
{
    public function __construct(private readonly ChannelQueries $channels) {}

    /** Canal inexistente: 200 com `null` (F3-2). */
    public function __invoke(string $searchQuery): JsonResponse|Response
    {
        $channel = $this->channels->byNameOrLink($searchQuery);

        return $channel === null ? JsonNull::response() : response()->json((new ChannelResource($channel))->resolve());
    }
}
