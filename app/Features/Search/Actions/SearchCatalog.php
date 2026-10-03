<?php

declare(strict_types=1);

namespace App\Features\Search\Actions;

use App\Features\Search\Data\SearchData;
use App\Features\Search\Data\SearchResult;
use App\Features\Search\Queries\SearchQueries;
use App\Shared\Support\Uri;

/** Canais (até 10) e depois vídeos (até 20); `value` em `encodeURI`, como no original. */
final class SearchCatalog
{
    public const CHANNEL_LIMIT = 10;

    public const VIDEO_LIMIT = 20;

    public function __construct(private readonly SearchQueries $queries) {}

    /** @return list<SearchResult> */
    public function __invoke(SearchData $data): array
    {
        $results = [];

        foreach ($this->queries->channels($data->term, self::CHANNEL_LIMIT) as $channel) {
            $results[] = new SearchResult(Uri::encode($channel['name']), $channel['name'], 'channel');
        }

        foreach ($this->queries->videos($data->term, self::VIDEO_LIMIT) as $video) {
            $results[] = new SearchResult(Uri::encode($video['youtube_id']), $video['title'], 'video');
        }

        return $results;
    }
}
