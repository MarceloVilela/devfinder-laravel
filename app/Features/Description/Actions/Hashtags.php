<?php

declare(strict_types=1);

namespace App\Features\Description\Actions;

/** `#tag` por tag distinta, na ordem em que aparecem, com espaço virando hífen. */
final class Hashtags
{
    /**
     * @param list<array{tags: list<string>}> $channels
     * @return list<string>
     */
    public static function from(array $channels): array
    {
        $hashtags = [];

        foreach ($channels as $channel) {
            foreach ($channel['tags'] as $tag) {
                $hashtags['#' . str_replace(' ', '-', $tag)] = true;
            }
        }

        return array_map('strval', array_keys($hashtags));
    }
}
