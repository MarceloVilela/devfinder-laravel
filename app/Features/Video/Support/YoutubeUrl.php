<?php

declare(strict_types=1);

namespace App\Features\Video\Support;

final class YoutubeUrl
{
    /** Id do vídeo no parâmetro `v=` (1 a 20 caracteres `[A-Za-z0-9_-]`), ou `null`. */
    public const ID_PATTERN = '/[?&]v=([A-Za-z0-9_-]{1,20})(?:&|$)/';

    /** Tira o parâmetro de rastreio `&pp=…` (paridade com o original e o v1). */
    public static function withoutTracking(string $url): string
    {
        return preg_replace('/&pp=[^&]*/', '', $url) ?? $url;
    }

    public static function idOf(string $url): ?string
    {
        return preg_match(self::ID_PATTERN, $url, $matches) === 1 ? $matches[1] : null;
    }

    public static function defaultThumbnail(string $youtubeId): string
    {
        return "https://i.ytimg.com/vi/{$youtubeId}/hqdefault.jpg";
    }
}
