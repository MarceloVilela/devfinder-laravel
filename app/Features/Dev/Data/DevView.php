<?php

declare(strict_types=1);

namespace App\Features\Dev\Data;

use Carbon\CarbonImmutable;

/** Dev como o contrato o expõe; os quatro vetores são ids de devs e canais ativos. */
final readonly class DevView
{
    /**
     * @param list<string> $likes
     * @param list<string> $dislikes
     * @param list<string> $follow
     * @param list<string> $ignore
     */
    public function __construct(
        public string $id,
        public string $username,
        public string $name,
        public string $bio,
        public string $avatar,
        public array $likes,
        public array $dislikes,
        public array $follow,
        public array $ignore,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}
}
