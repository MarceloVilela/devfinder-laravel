<?php

declare(strict_types=1);

namespace App\Features\Video\Data;

use Carbon\CarbonImmutable;

/** Vídeo com o canal já resolvido (`channel` e `channel_url` saem do JOIN, não de colunas de `videos`). */
final readonly class VideoView
{
    public function __construct(
        public string $id,
        public string $title,
        public string $url,
        public string $channelId,
        public string $channelName,
        public string $channelUrl,
        public string $thumbnail,
        public ?int $viewnum,
        public ?CarbonImmutable $publishedAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}
}
