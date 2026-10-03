<?php

declare(strict_types=1);

use App\Shared\Pagination\Page;

it('resolve a página pedida, o mínimo 1 e o máximo da última', function (int $requested, int $total, int $expected): void {
    expect(Page::resolve($requested, $total)->number)->toBe($expected);
})->with([
    [1, 35, 1], [2, 35, 2], [3, 35, 2], [999, 35, 2], [0, 35, 1], [-5, 35, 1],
    [1, 0, 1], [5, 0, 1], [1, 30, 1], [2, 30, 1], [2, 31, 2], [PHP_INT_MAX, 35, 2],
]);

it('calcula totalPages (mínimo 1) e offset', function (): void {
    expect(Page::resolve(1, 0)->totalPages())->toBe(1)
        ->and(Page::resolve(1, 30)->totalPages())->toBe(1)
        ->and(Page::resolve(1, 31)->totalPages())->toBe(2)
        ->and(Page::resolve(2, 35)->offset())->toBe(30)
        ->and(Page::resolve(1, 35)->offset())->toBe(0);
});
