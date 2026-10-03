<?php

declare(strict_types=1);

namespace App\Features\Search\Data;

final readonly class SearchData
{
    public function __construct(public string $term) {}
}
