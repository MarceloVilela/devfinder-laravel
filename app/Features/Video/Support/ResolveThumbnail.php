<?php

declare(strict_types=1);

namespace App\Features\Video\Support;

final class ResolveThumbnail
{
    /**
     * `hq720_custom_N` (frame assinado que costuma renderizar quebrado) vira o `hqdefault` do mesmo vídeo; vazia vira o padrão do
     * YouTube; qualquer outra fica como veio (paridade com o v1 e com o original).
     */
    public static function for(string $thumbnail, string $youtubeId): string
    {
        if (preg_match('#/vi/([^/]+)/hq720_custom_\d+\.jpg#', $thumbnail, $matches) === 1) {
            return YoutubeUrl::defaultThumbnail($matches[1]);
        }

        return $thumbnail !== '' ? $thumbnail : YoutubeUrl::defaultThumbnail($youtubeId);
    }
}
