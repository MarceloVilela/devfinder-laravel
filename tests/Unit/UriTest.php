<?php

declare(strict_types=1);

use App\Shared\Support\NormText;
use App\Shared\Support\Uri;

// Valores conferidos contra `encodeURI` do Node.
it('codifica como o encodeURI do JavaScript', function (string $input, string $expected): void {
    expect(Uri::encode($input))->toBe($expected);
})->with([
    ['Canal Alpha', 'Canal%20Alpha'],
    ['vidalpha01', 'vidalpha01'],
    ['Vídeo Ação', 'V%C3%ADdeo%20A%C3%A7%C3%A3o'],
    ["a;b,c/d?e:f@g&h=i+j\$k#l", "a;b,c/d?e:f@g&h=i+j\$k#l"],
    ["-_.!~*'()", "-_.!~*'()"],
    ['a%b"c<d>e[f]g{h}|\\^`', 'a%25b%22c%3Cd%3Ee%5Bf%5Dg%7Bh%7D%7C%5C%5E%60'],
    ['😀', '%F0%9F%98%80'],
]);

it('escapa % _ e \ do termo do LIKE', function (): void {
    expect(NormText::containsPattern('a%b_c\\d'))->toBe('%a\\%b\\_c\\\\d%')
        ->and(NormText::containsPattern('abc'))->toBe('%abc%');
});
