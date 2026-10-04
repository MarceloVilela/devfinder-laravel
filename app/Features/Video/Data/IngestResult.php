<?php

declare(strict_types=1);

namespace App\Features\Video\Data;

/** Resumo da ingestão no formato do contrato (`VideoRefreshResult`). */
final readonly class IngestResult
{
    /**
     * @param list<VideoView> $videosAdded
     * @param list<VideoView> $videosFounded
     * @param list<array<string, string>> $errors
     */
    public function __construct(
        public array $videosAdded,
        public array $videosFounded,
        public array $errors,
    ) {}

    /** @return array{videosAdded: list<array<string, mixed>>, videosFounded: list<array<string, mixed>>, errors: list<array<string, string>>} */
    public function toContract(): array
    {
        return [
            'videosAdded' => array_map(static fn(VideoView $v): array => $v->toContract(), $this->videosAdded),
            'videosFounded' => array_map(static fn(VideoView $v): array => $v->toContract(), $this->videosFounded),
            'errors' => $this->errors,
        ];
    }
}
