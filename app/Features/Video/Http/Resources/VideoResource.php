<?php

declare(strict_types=1);

namespace App\Features\Video\Http\Resources;

use App\Features\Video\Data\VideoView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Schema `Video` do contrato. */
final class VideoResource extends JsonResource
{
    public function __construct(private readonly VideoView $video)
    {
        parent::__construct($video);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->video->toContract();
    }
}
