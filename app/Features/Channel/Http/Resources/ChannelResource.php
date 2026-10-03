<?php

declare(strict_types=1);

namespace App\Features\Channel\Http\Resources;

use App\Features\Channel\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Schema `Channel` do contrato. */
final class ChannelResource extends JsonResource
{
    public function __construct(private readonly Channel $channel)
    {
        parent::__construct($channel);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            '_id' => $this->channel->id,
            'name' => $this->channel->name,
            'userGithub' => $this->channel->user_github,
            'link' => $this->channel->link,
            'description' => $this->channel->description,
            'category' => $this->channel->category,
            'tags' => $this->channel->tags->pluck('name')->values()->all(),
            'avatar' => $this->channel->avatar,
            'likes' => [],
            'deslikes' => [],
            'createdAt' => $this->channel->created_at->utc()->toIso8601String(),
            'updatedAt' => $this->channel->updated_at->utc()->toIso8601String(),
        ];
    }
}
