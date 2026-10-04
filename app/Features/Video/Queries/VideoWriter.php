<?php

declare(strict_types=1);

namespace App\Features\Video\Queries;

use App\Shared\Support\NormText;
use Illuminate\Support\Facades\DB;

final class VideoWriter
{
    /**
     * Canal por nome **ou** link **ou** link alternativo, exatos (paridade com o original: dois critérios de igualdade
     * diferentes, não o mesmo termo nos dois lados). 1 query.
     */
    public function channelIdFor(string $channelName, string $channelUrl): ?string
    {
        $id = DB::table('channels')
            ->whereNull('deleted_at')
            ->whereRaw(
                '(' . NormText::equals('name') . ' or ' . NormText::equals('link') . ' or ' . NormText::equals('alternative_link') . ')',
                [$channelName, $channelUrl, $channelUrl],
            )
            ->orderBy('created_at')
            ->orderBy('id')
            ->value('id');

        return is_string($id) ? $id : null;
    }

    /** `youtube_id` do vídeo ativo com este `url` exato, ou `null`. 1 query. */
    public function youtubeIdOfUrl(string $url): ?string
    {
        $id = DB::table('videos')->whereNull('deleted_at')->where('url', $url)->value('youtube_id');

        return is_string($id) ? $id : null;
    }

    /**
     * 1 query.
     *
     * @param array<string, mixed> $data
     */
    public function insert(array $data): void
    {
        DB::table('videos')->insert($data + ['created_at' => now(), 'updated_at' => now()]);
    }
}
