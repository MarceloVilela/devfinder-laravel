<?php

declare(strict_types=1);

namespace App\Features\Video\Data;

final readonly class StoreVideoData
{
    public function __construct(
        public string $title,
        public string $url,
        public string $channel,
        public string $channelUrl,
        public string $thumbnail,
    ) {}
}
