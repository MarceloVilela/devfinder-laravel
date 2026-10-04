<?php

declare(strict_types=1);

namespace App\Features\Channel\Data;

final readonly class StoreChannelData
{
    /** @param list<string> $tags */
    public function __construct(
        public string $link,
        public string $title,
        public ?string $description,
        public string $category,
        public array $tags,
        public ?string $userGithub,
        public ?string $avatar,
    ) {}
}
