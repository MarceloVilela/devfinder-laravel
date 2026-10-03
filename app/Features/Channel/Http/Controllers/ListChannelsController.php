<?php

declare(strict_types=1);

namespace App\Features\Channel\Http\Controllers;

use App\Features\Channel\Http\Resources\ChannelResource;
use App\Features\Channel\Queries\ChannelQueries;
use Illuminate\Http\JsonResponse;

final class ListChannelsController
{
    public function __construct(private readonly ChannelQueries $channels) {}

    public function __invoke(): JsonResponse
    {
        return response()->json(ChannelResource::collection($this->channels->all())->resolve());
    }
}
