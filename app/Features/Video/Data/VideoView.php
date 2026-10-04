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

    /**
     * Schema `Video` do contrato (fonte única: o `VideoResource` e os erros 409 usam este método).
     *
     * @return array<string, mixed>
     */
    public function toContract(): array
    {
        return [
            '_id' => $this->id,
            'title' => $this->title,
            'url' => $this->url,
            'channel_id' => $this->channelId,
            'channel' => $this->channelName,
            'channel_url' => $this->channelUrl,
            'thumbnail' => $this->thumbnail,
            'viewnum' => $this->viewnum,
            'date' => $this->publishedAt?->toIso8601String(),
            'createdAt' => $this->createdAt->toIso8601String(),
            'updatedAt' => $this->updatedAt->toIso8601String(),
        ];
    }
}
