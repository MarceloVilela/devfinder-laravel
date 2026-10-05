<?php

declare(strict_types=1);

namespace App\Features\Description\Queries;

use App\Shared\Support\NormText;
use Illuminate\Support\Facades\DB;
use stdClass;

final class DescriptionQueries
{
    /**
     * Canais dos 30 vídeos mais recentes, em ordem de aparição e sem repetição, com as tags (2 queries).
     *
     * @return list<array{name: string, tags: list<string>}>
     */
    public function trendingChannels(int $limit): array
    {
        $rows = DB::table('videos')
            ->join('channels', 'channels.id', '=', 'videos.channel_id')
            ->whereNull('videos.deleted_at')
            ->whereNull('channels.deleted_at')
            ->orderByDesc('videos.created_at')
            ->orderByDesc('videos.id')
            ->limit($limit)
            ->get(['channels.id', 'channels.name'])
            ->all();

        $channels = [];

        foreach ($rows as $row) {
            $channels[self::str($row, 'id')] ??= self::str($row, 'name');
        }

        return $this->withTags($channels);
    }

    /**
     * Todos os canais por categoria e nome, com as tags (2 queries).
     *
     * @return list<array{category: string, name: string, tags: list<string>}>
     */
    public function channelsByCategory(): array
    {
        $rows = DB::table('channels')
            ->whereNull('deleted_at')
            ->orderByRaw(NormText::sortKey('category'))
            ->orderByRaw(NormText::sortKey('name'))
            ->orderBy('id')
            ->get(['id', 'name', 'category'])
            ->all();

        $names = [];
        $categories = [];

        foreach ($rows as $row) {
            $names[self::str($row, 'id')] = self::str($row, 'name');
            $categories[self::str($row, 'id')] = self::str($row, 'category');
        }

        $result = [];

        foreach ($this->withTags($names) as $i => $channel) {
            $id = array_keys($names)[$i];
            $result[] = ['category' => $categories[$id], 'name' => $channel['name'], 'tags' => $channel['tags']];
        }

        return $result;
    }

    /**
     * @param array<string, string> $names id do canal => nome, na ordem desejada
     * @return list<array{name: string, tags: list<string>}>
     */
    private function withTags(array $names): array
    {
        if ($names === []) {
            return [];
        }

        $tags = [];

        $rows = DB::table('channel_tag')
            ->join('tags', 'tags.id', '=', 'channel_tag.tag_id')
            ->whereNull('tags.deleted_at')
            ->whereIn('channel_tag.channel_id', array_keys($names))
            ->orderByRaw(NormText::sortKey('tags.name'))
            ->orderByRaw(NormText::tieBreak('tags.name'))
            ->get(['channel_tag.channel_id', 'tags.name']);

        foreach ($rows as $row) {
            $tags[self::str($row, 'channel_id')][] = self::str($row, 'name');
        }

        $result = [];

        foreach ($names as $id => $name) {
            $result[] = ['name' => $name, 'tags' => $tags[$id] ?? []];
        }

        return $result;
    }

    private static function str(stdClass $row, string $column): string
    {
        $value = $row->{$column} ?? null;

        return is_string($value) ? $value : '';
    }
}
