<?php

declare(strict_types=1);

namespace App\Features\Video\Queries;

use App\Features\Video\Data\VideoView;
use App\Shared\Pagination\Page;
use App\Shared\Pagination\Paginated;
use App\Shared\Support\NormText;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final class VideoQueries
{
    /**
     * 2 queries: contagem e página.
     *
     * @return Paginated<VideoView>
     */
    public function trending(int $requested): Paginated
    {
        return $this->paginate($this->base(), $requested);
    }

    /**
     * 3 queries: canal, contagem e página (1 se o canal não existe).
     *
     * @return Paginated<VideoView>
     */
    public function byChannel(?string $channelName, int $requested): Paginated
    {
        $channelId = $channelName === null ? null : DB::table('channels')
            ->whereNull('deleted_at')
            ->whereRaw(
                '(' . NormText::equals('name') . ' or ' . NormText::equals('link') . ' or ' . NormText::equals('alternative_link') . ')',
                [$channelName, $channelName, $channelName],
            )
            ->orderBy('created_at')
            ->orderBy('id')
            ->value('id');

        if (! is_string($channelId)) {
            return Paginated::empty();
        }

        return $this->paginate($this->base()->where('videos.channel_id', $channelId), $requested);
    }

    /** 1 query. */
    public function byYoutubeId(string $youtubeId): ?VideoView
    {
        $row = $this->base()->where('videos.youtube_id', $youtubeId)->first($this->columns());

        return $row === null ? null : $this->view($row);
    }

    private function base(): Builder
    {
        return DB::table('videos')
            ->join('channels', 'channels.id', '=', 'videos.channel_id')
            ->whereNull('videos.deleted_at')
            ->whereNull('channels.deleted_at');
    }

    /** @return Paginated<VideoView> */
    private function paginate(Builder $query, int $requested): Paginated
    {
        $page = Page::resolve($requested, $query->count());

        $rows = $query
            ->orderByDesc('videos.created_at')
            ->orderByDesc('videos.id')
            ->offset($page->offset())
            ->limit($page->perPage)
            ->get($this->columns())
            ->all();

        return new Paginated(array_values(array_map($this->view(...), $rows)), $page);
    }

    /** @return list<string> */
    private function columns(): array
    {
        return [
            'videos.id', 'videos.title', 'videos.url', 'videos.channel_id', 'channels.name as channel_name',
            'channels.link as channel_url', 'videos.thumbnail', 'videos.viewnum', 'videos.published_at',
            'videos.created_at', 'videos.updated_at',
        ];
    }

    private function view(stdClass $row): VideoView
    {
        return new VideoView(
            id: self::str($row, 'id'),
            title: self::str($row, 'title'),
            url: self::str($row, 'url'),
            channelId: self::str($row, 'channel_id'),
            channelName: self::str($row, 'channel_name'),
            channelUrl: self::str($row, 'channel_url'),
            thumbnail: self::str($row, 'thumbnail'),
            viewnum: is_numeric($row->viewnum ?? null) ? (int) $row->viewnum : null,
            publishedAt: is_string($row->published_at ?? null) ? CarbonImmutable::parse($row->published_at)->utc() : null,
            createdAt: CarbonImmutable::parse(self::str($row, 'created_at'))->utc(),
            updatedAt: CarbonImmutable::parse(self::str($row, 'updated_at'))->utc(),
        );
    }

    private static function str(stdClass $row, string $column): string
    {
        $value = $row->{$column} ?? null;

        return is_string($value) ? $value : '';
    }
}
