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
     * 2 queries (+1 com `?user=`, +1 do middleware com token): contagem e página.
     *
     * @return Paginated<VideoView>
     */
    public function trending(int $requested, ?string $actorId = null, ?string $username = null): Paginated
    {
        $devId = $actorId ?? $this->devIdByUsername($username);
        $query = $this->base();

        if ($devId !== null) {
            $query->whereNotIn(
                'videos.channel_id',
                DB::table('channel_reactions')->select('channel_id')->where('dev_id', $devId)->where('type', 'ignore'),
            );
        }

        return $this->paginate($query, $requested);
    }

    /**
     * Vídeos dos canais com `follow` do dev, na ordem do trending (F5-11). 2 queries (+1 do middleware).
     *
     * @return Paginated<VideoView>
     */
    public function subscribed(string $devId, int $requested): Paginated
    {
        return $this->paginate(
            $this->base()->whereIn(
                'videos.channel_id',
                DB::table('channel_reactions')->select('channel_id')->where('dev_id', $devId)->where('type', 'follow'),
            ),
            $requested,
        );
    }

    /** `?user=` identifica o dev sem token (paridade com o v1 e o original); desconhecido segue anônimo. 1 query. */
    private function devIdByUsername(?string $username): ?string
    {
        if ($username === null || $username === '') {
            return null;
        }

        $id = DB::table('devs')->whereNull('deleted_at')->whereRaw(NormText::equals('username'), [$username])->value('id');

        return is_string($id) ? $id : null;
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

    /**
     * Vídeos ativos (de canal ativo) por `youtube_id`, em blocos de 200. 1 query por bloco.
     *
     * @param list<string> $youtubeIds
     * @return array<string, VideoView> youtube_id => vídeo
     */
    public function byYoutubeIds(array $youtubeIds): array
    {
        $views = [];

        foreach (array_chunk(array_values(array_unique($youtubeIds)), 200) as $chunk) {
            foreach ($this->base()->whereIn('videos.youtube_id', $chunk)->get(array_merge($this->columns(), ['videos.youtube_id as youtube_id'])) as $row) {
                $views[self::textOf($row, 'youtube_id')] = $this->view($row);
            }
        }

        return $views;
    }

    private static function textOf(stdClass $row, string $column): string
    {
        $value = $row->{$column} ?? null;

        return is_string($value) ? $value : '';
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
