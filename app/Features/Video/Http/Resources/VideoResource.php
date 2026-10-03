<?php

declare(strict_types=1);

namespace App\Features\Video\Http\Resources;

use App\Features\Video\Data\VideoView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Schema `Video` do contrato. */
final class VideoResource extends JsonResource
{
    public function __construct(private readonly VideoView $video)
    {
        parent::__construct($video);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            '_id' => $this->video->id,
            'title' => $this->video->title,
            'url' => $this->video->url,
            'channel_id' => $this->video->channelId,
            'channel' => $this->video->channelName,
            'channel_url' => $this->video->channelUrl,
            'thumbnail' => $this->video->thumbnail,
            'viewnum' => $this->video->viewnum,
            'date' => $this->video->publishedAt?->toIso8601String(),
            'createdAt' => $this->video->createdAt->toIso8601String(),
            'updatedAt' => $this->video->updatedAt->toIso8601String(),
        ];
    }
}
