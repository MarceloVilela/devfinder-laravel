<?php

declare(strict_types=1);

namespace App\Shared\Pagination;

/** Página efetivamente servida (P-3): valor inválido vira 1 e além do fim vira a última. */
final readonly class Page
{
    public const PER_PAGE = 30;

    private function __construct(
        public int $number,
        public int $total,
        public int $perPage,
    ) {}

    public static function resolve(int $requested, int $total, int $perPage = self::PER_PAGE): self
    {
        $last = max(1, intdiv($total + $perPage - 1, $perPage));

        return new self(min(max(1, $requested), $last), $total, $perPage);
    }

    public function totalPages(): int
    {
        return max(1, intdiv($this->total + $this->perPage - 1, $this->perPage));
    }

    public function offset(): int
    {
        return ($this->number - 1) * $this->perPage;
    }
}
