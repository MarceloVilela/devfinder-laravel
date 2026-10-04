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
        return $this->dev->toContract();
    }
}
