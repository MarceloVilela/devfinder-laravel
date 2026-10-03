<?php

declare(strict_types=1);

namespace App\Features\Search\Queries;

use App\Shared\Support\NormText;
use Illuminate\Support\Facades\DB;
use stdClass;

final class SearchQueries
{
    /** @return list<array{name: string}> */
    public function channels(string $term, int $limit): array
    {
        $pattern = NormText::containsPattern($term);

        $rows = DB::table('channels')
            ->whereNull('deleted_at')
            ->whereRaw('(' . NormText::like('name') . ' or ' . NormText::like('link') . ')', [$pattern, $pattern])
            ->orderByRaw('norm_text(name)')
            ->orderBy('id')
            ->limit($limit)
            ->get(['name'])
            ->all();

        return array_values(array_map(static fn(stdClass $r): array => ['name' => is_string($r->name) ? $r->name : ''], $rows));
    }

    /** @return list<array{youtube_id: string, title: string}> */
    public function videos(string $term, int $limit): array
    {
        $rows = DB::table('videos')
            ->join('channels', 'channels.id', '=', 'videos.channel_id')
            ->whereNull('videos.deleted_at')
            ->whereNull('channels.deleted_at')
            ->whereRaw(NormText::like('videos.title'), [NormText::containsPattern($term)])
            ->orderByDesc('videos.created_at')
            ->orderByDesc('videos.id')
            ->limit($limit)
            ->get(['videos.youtube_id', 'videos.title'])
            ->all();

        return array_values(array_map(static fn(stdClass $r): array => [
            'youtube_id' => is_string($r->youtube_id) ? $r->youtube_id : '',
            'title' => is_string($r->title) ? $r->title : '',
        ], $rows));
    }
}
