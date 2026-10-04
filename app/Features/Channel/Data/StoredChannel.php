<?php

declare(strict_types=1);

namespace App\Features\Channel\Data;

final readonly class StoredChannel
{
    public function __construct(
        public string $id,
        public bool $created,
    ) {}
}
