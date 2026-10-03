<?php

declare(strict_types=1);

namespace App\Features\Dev\Queries;

use App\Features\Dev\Data\DevView;
use App\Shared\Pagination\Page;
use App\Shared\Pagination\Paginated;
use App\Shared\Support\NormText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

final class DevQueries
{
    private const COLUMNS = ['id', 'username', 'name', 'bio', 'avatar', 'created_at', 'updated_at'];

    /**
     * 4 queries: contagem, página, reações de dev e reações de canal da página.
     *
     * @return Paginated<DevView>
     */
    public function page(int $requested): Paginated
    {
        $total = DB::table('devs')->whereNull('deleted_at')->count();
        $page = Page::resolve($requested, $total);

        $rows = DB::table('devs')
            ->select(self::COLUMNS)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->offset($page->offset())
            ->limit($page->perPage)
            ->get()
            ->all();

        return new Paginated($this->hydrate(array_values($rows)), $page);
    }

    /** 3 queries (1 se não existe). */
    public function byUsername(string $username): ?DevView
    {
        $row = DB::table('devs')
            ->select(self::COLUMNS)
            ->whereNull('deleted_at')
            ->whereRaw(NormText::equals('username'), [$username])
            ->first();

        return $row === null ? null : $this->hydrate([$row])[0];
    }

    /**
     * @param list<stdClass> $rows
     * @return list<DevView>
     */
    private function hydrate(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn(stdClass $r): string => self::str($r, 'id'), $rows);

        $devReactions = DB::table('dev_reactions as r')
            ->join('devs as t', fn($join) => $join->on('t.id', '=', 'r.target_dev_id')->whereNull('t.deleted_at'))
            ->whereIn('r.dev_id', $ids)
            ->orderBy('r.created_at')
            ->orderBy('r.target_dev_id')
            ->get(['r.dev_id as owner', 'r.target_dev_id as target', 'r.type']);

        $channelReactions = DB::table('channel_reactions as r')
            ->join('channels as c', fn($join) => $join->on('c.id', '=', 'r.channel_id')->whereNull('c.deleted_at'))
            ->whereIn('r.dev_id', $ids)
            ->orderBy('r.created_at')
            ->orderBy('r.channel_id')
            ->get(['r.dev_id as owner', 'r.channel_id as target', 'r.type']);

        return array_map(fn(stdClass $r): DevView => new DevView(
            id: self::str($r, 'id'),
            username: self::str($r, 'username'),
            name: self::str($r, 'name'),
            bio: is_string($r->bio ?? null) ? $r->bio : '',
            avatar: self::str($r, 'avatar'),
            likes: self::targets($devReactions, self::str($r, 'id'), 'like'),
            dislikes: self::targets($devReactions, self::str($r, 'id'), 'dislike'),
            follow: self::targets($channelReactions, self::str($r, 'id'), 'follow'),
            ignore: self::targets($channelReactions, self::str($r, 'id'), 'ignore'),
            createdAt: CarbonImmutable::parse(self::str($r, 'created_at'))->utc(),
            updatedAt: CarbonImmutable::parse(self::str($r, 'updated_at'))->utc(),
        ), $rows);
    }

    /**
     * @param \Illuminate\Support\Collection<int, stdClass> $reactions
     * @return list<string>
     */
    private static function targets($reactions, string $owner, string $type): array
    {
        $ids = [];

        foreach ($reactions as $reaction) {
            if (self::str($reaction, 'owner') === $owner && self::str($reaction, 'type') === $type) {
                $ids[] = self::str($reaction, 'target');
            }
        }

        return $ids;
    }

    private static function str(stdClass $row, string $column): string
    {
        $value = $row->{$column} ?? null;

        return is_string($value) ? $value : '';
    }
}
