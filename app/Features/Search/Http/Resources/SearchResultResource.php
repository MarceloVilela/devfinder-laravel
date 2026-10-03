<?php

declare(strict_types=1);

namespace App\Features\Search\Http\Resources;

use App\Features\Search\Data\SearchResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Schema `SearchResult` do contrato. */
final class SearchResultResource extends JsonResource
{
    public function __construct(private readonly SearchResult $result)
    {
        parent::__construct($result);
    }

    /** @return array<string, string> */
    public function toArray(Request $request): array
    {
        return ['value' => $this->result->value, 'label' => $this->result->label, 'type' => $this->result->type];
    }
}
