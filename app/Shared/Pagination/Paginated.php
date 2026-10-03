<?php

declare(strict_types=1);

namespace App\Shared\Pagination;

/** @template T */
final readonly class Paginated
{
    /** @param list<T> $items */
    public function __construct(
        public array $items,
        public Page $page,
    ) {}

    /**
     * Envelope do contrato: `{docs,total,itemsPerPage}` mais `page` e `totalPages` (D-11).
     *
     * @param callable(T): array<string, mixed> $map
     * @return array<string, mixed>
     */
    public function envelope(callable $map): array
    {
        return [
            'docs' => array_map($map, $this->items),
            'total' => $this->page->total,
            'itemsPerPage' => $this->page->perPage,
            'page' => $this->page->number,
            'totalPages' => $this->page->totalPages(),
        ];
    }

    /** @return self<never> */
    public static function empty(): self
    {
        return new self([], Page::resolve(1, 0));
    }
}
