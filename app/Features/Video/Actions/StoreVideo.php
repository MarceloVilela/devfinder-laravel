<?php

declare(strict_types=1);

namespace App\Features\Video\Actions;

use App\Features\Video\Data\StoreVideoData;
use App\Features\Video\Data\VideoView;
use App\Features\Video\Exceptions\ChannelNotFoundForVideo;
use App\Features\Video\Exceptions\VideoAlreadyExists;
use App\Features\Video\Queries\VideoQueries;
use App\Features\Video\Queries\VideoWriter;
use App\Features\Video\Support\YoutubeUrl;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** `POST /video` (F5-10): canal por nome ou link, 409 se o `url` já existe, thumbnail padrão do YouTube se vier vazia. */
final class StoreVideo
{
    public function __construct(
        private readonly VideoWriter $writer,
        private readonly VideoQueries $queries,
    ) {}

    /**
     * @throws ChannelNotFoundForVideo
     * @throws VideoAlreadyExists
     */
    public function __invoke(StoreVideoData $data): VideoView
    {
        $url = YoutubeUrl::withoutTracking($data->url);
        $thumbnail = YoutubeUrl::withoutTracking($data->thumbnail);
        $youtubeId = YoutubeUrl::idOf($url) ?? throw new RuntimeException('url sem v= passou pela validação');

        $channelId = $this->writer->channelIdFor($data->channel, $data->channelUrl)
            ?? throw new ChannelNotFoundForVideo($data->title, $url, $data->channel, $data->channelUrl, $thumbnail);

        $existingId = $this->writer->youtubeIdOfUrl($url);

        if ($existingId !== null) {
            throw new VideoAlreadyExists($data->title, $this->existing($existingId));
        }

        try {
            DB::transaction(fn() => $this->writer->insert([
                'youtube_id' => $youtubeId,
                'title' => $data->title,
                'url' => $url,
                'channel_id' => $channelId,
                'thumbnail' => $thumbnail !== '' ? $thumbnail : YoutubeUrl::defaultThumbnail($youtubeId),
            ]));
        } catch (UniqueConstraintViolationException $e) {
            // (A transação em volta é o savepoint que deixa a conexão utilizável depois da violação.)
            // Corrida, ou outro `url` com o mesmo id do YouTube: a rede de segurança do UNIQUE vira o mesmo 409 (F5-14).
            throw new VideoAlreadyExists($data->title, $this->existing($youtubeId), $e);
        }

        return $this->existing($youtubeId);
    }

    private function existing(string $youtubeId): VideoView
    {
        return $this->queries->byYoutubeId($youtubeId) ?? throw new RuntimeException("vídeo {$youtubeId} sumiu depois de gravado");
    }
}
