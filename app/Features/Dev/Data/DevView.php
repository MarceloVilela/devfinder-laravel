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

    /**
     * Schema `Dev` do contrato (fonte única: o `DevResource` e as respostas de outras features usam este método).
     *
     * @return array<string, mixed>
     */
    public function toContract(): array
    {
        return [
            '_id' => $this->id,
            'name' => $this->name,
            'user' => $this->username,
            'bio' => $this->bio,
            'avatar' => $this->avatar,
            'likes' => $this->likes,
            'deslikes' => $this->dislikes,
            'follow' => $this->follow,
            'ignore' => $this->ignore,
            'createdAt' => $this->createdAt->toIso8601String(),
            'updatedAt' => $this->updatedAt->toIso8601String(),
        ];
    }
}
