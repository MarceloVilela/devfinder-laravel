<?php

declare(strict_types=1);

namespace App\Shared\Support;

final class Uri
{
    /** Equivalente ao `encodeURI` do JavaScript (mantém `;,/?:@&=+$#` e `-_.!~*'()`), que o original usa em `/search`. */
    public static function encode(string $value): string
    {
        return strtr(rawurlencode($value), [
            '%3B' => ';', '%2C' => ',', '%2F' => '/', '%3F' => '?', '%3A' => ':', '%40' => '@', '%26' => '&',
            '%3D' => '=', '%2B' => '+', '%24' => '$', '%23' => '#', '%21' => '!', '%2A' => '*', '%27' => "'",
            '%28' => '(', '%29' => ')',
        ]);
    }
}
