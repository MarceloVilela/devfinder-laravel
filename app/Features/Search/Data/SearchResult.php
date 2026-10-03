<?php

declare(strict_types=1);

namespace App\Features\Search\Data;

final readonly class SearchResult
{
    /** @param 'channel'|'video' $type */
    public function __construct(
        public string $value,
        public string $label,
        public string $type,
    ) {}
}
