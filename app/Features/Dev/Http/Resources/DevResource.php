<?php

declare(strict_types=1);

namespace App\Features\Dev\Http\Resources;

use App\Features\Dev\Data\DevView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Schema `Dev` do contrato. */
final class DevResource extends JsonResource
{
    public function __construct(private readonly DevView $dev)
    {
        parent::__construct($dev);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            '_id' => $this->dev->id,
            'name' => $this->dev->name,
            'user' => $this->dev->username,
            'bio' => $this->dev->bio,
            'avatar' => $this->dev->avatar,
            'likes' => $this->dev->likes,
            'deslikes' => $this->dev->dislikes,
            'follow' => $this->dev->follow,
            'ignore' => $this->dev->ignore,
            'createdAt' => $this->dev->createdAt->toIso8601String(),
            'updatedAt' => $this->dev->updatedAt->toIso8601String(),
        ];
    }
}
