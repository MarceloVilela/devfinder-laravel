<?php

declare(strict_types=1);

namespace App\Features\Video\Actions;

use App\Features\Video\Data\IngestResult;
use App\Features\Video\Data\VideoView;
use App\Features\Video\Queries\VideoQueries;
use App\Features\Video\Queries\VideoWriter;
use App\Features\Video\Support\ResolveThumbnail;
use App\Features\Video\Support\YoutubeUrl;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ingestão em lote (F6-1): a regra única da rota `POST /video/refresh` e do comando agendado `video:refresh`.
 * Por candidato: canal por nome ou link, `url` existente vai em `videosFounded`, senão grava. Idempotente: rodar de novo o mesmo lote não
 * grava nada e devolve tudo em `videosFounded`. Cada candidato é isolado (F6-3); falha de banco repetida aborta o lote (F6-4).
 *
 * @phpstan-type Item array{valid: bool, reason: string, title: string, url: string, channel: string, channel_url: string, thumbnail: string, id: string}
 */
class IngestVideos
{
    private const MAX_CONSECUTIVE_CONNECTION_FAILURES = 3;

    public function __construct(
        private readonly VideoWriter $writer,
        private readonly VideoQueries $queries,
    ) {}

    /**
     * @param list<mixed> $candidates itens `{title, url, channel, channel_url, thumbnail}` (o comando já trocou `channel_name` por `channel`)
     *
     * @throws Throwable falha de conexão com o banco repetida
     */
    public function __invoke(array $candidates): IngestResult
    {
        $items = array_map($this->normalize(...), $candidates);
        $existing = $this->writer->youtubeIdsByUrl(array_values(array_filter(array_map(static fn(array $i): string => $i['valid'] ? $i['url'] : '', $items))));

        $channels = [];
        $inBatch = [];
        $order = [];
        $errors = [];
        $connectionFailures = 0;

        foreach ($items as $item) {
            if (! $item['valid']) {
                $errors[] = $this->error($item, $item['reason']);

                continue;
            }

            try {
                $key = $item['channel'] . "\0" . $item['channel_url'];
                $channels[$key] = array_key_exists($key, $channels) ? $channels[$key] : $this->writer->channelIdFor($item['channel'], $item['channel_url']);

                if ($channels[$key] === null) {
                    $errors[] = $this->error($item, "channel({$item['channel']}) not found, for: {$item['title']}");
                    $connectionFailures = 0;

                    continue;
                }

                $known = $existing[$item['url']] ?? $inBatch[$item['url']] ?? null;

                if ($known !== null) {
                    $order[] = ['found', $known];
                    $connectionFailures = 0;

                    continue;
                }

                $channelId = $channels[$key];
                $order[] = [$this->store($item, $channelId), $item['id']];
                $inBatch[$item['url']] = $item['id'];
                $connectionFailures = 0;
            } catch (Throwable $e) {
                if (self::isConnectionFailure($e) && ++$connectionFailures >= self::MAX_CONSECUTIVE_CONNECTION_FAILURES) {
                    throw $e;
                }

                // Um candidato que falha não derruba o lote; o motivo exato vai só para o log, nunca para a resposta.
                Log::warning('ingestão: candidato falhou', ['url' => $item['url'], 'cause' => $e::class . ': ' . $e->getMessage()]);
                $errors[] = $this->error($item, "could not persist video({$item['title']})");
            }
        }

        return $this->result($order, $errors);
    }

    /**
     * @param Item $item
     * @return 'added'|'found'
     */
    private function store(array $item, string $channelId): string
    {
        try {
            // A transação é o savepoint por candidato: uma violação de UNIQUE não aborta o resto do lote.
            DB::transaction(fn() => $this->writer->insert([
                'youtube_id' => $item['id'],
                'title' => $item['title'],
                'url' => $item['url'],
                'channel_id' => $channelId,
                'thumbnail' => ResolveThumbnail::for($item['thumbnail'], $item['id']),
            ]));
        } catch (UniqueConstraintViolationException) {
            // Corrida, ou outro `url` com o mesmo id do YouTube: já existe, conta como encontrado (F6-3).
            return 'found';
        }

        return 'added';
    }

    /**
     * @param list<array{0: string, 1: string}> $order
     * @param list<array<string, string>> $errors
     */
    private function result(array $order, array $errors): IngestResult
    {
        $views = $this->queries->byYoutubeIds(array_map(static fn(array $o): string => $o[1], $order));
        $added = [];
        $found = [];

        foreach ($order as [$kind, $youtubeId]) {
            $view = $views[$youtubeId] ?? null;

            if (! $view instanceof VideoView) {
                $errors[] = ['errorMessage' => "video {$youtubeId} exists but its channel is unavailable"];

                continue;
            }

            $kind === 'added' ? $added[] = $view : $found[] = $view;
        }

        return new IngestResult($added, $found, $errors);
    }

    /** @return Item */
    private function normalize(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $text = static fn(string $key): string => is_string($raw[$key] ?? null) ? $raw[$key] : '';
        $item = [
            'valid' => true,
            'reason' => '',
            'title' => $text('title'),
            'url' => YoutubeUrl::withoutTracking($text('url')),
            'channel' => $text('channel'),
            'channel_url' => $text('channel_url'),
            'thumbnail' => YoutubeUrl::withoutTracking($text('thumbnail')),
            'id' => '',
        ];
        $item['id'] = YoutubeUrl::idOf($item['url']) ?? '';

        $reason = match (true) {
            $raw === [] => 'candidate is not an object',
            $item['title'] === '' => 'title is required',
            $item['id'] === '' => 'url must carry the video id in v= (1 to 20 characters)',
            strlen($item['title']) > 500 || strlen($item['url']) > 500 || strlen($item['thumbnail']) > 500 || strlen($item['channel_url']) > 500 => 'text too long',
            strlen($item['channel']) > 255 => 'text too long',
            default => '',
        };

        if ($reason !== '') {
            $item['valid'] = false;
            $item['reason'] = "invalid video({$item['title']}): {$reason}";
        }

        return $item;
    }

    /**
     * @param Item $item
     * @return array<string, string>
     */
    private function error(array $item, string $message): array
    {
        return [
            'errorMessage' => $message,
            'title' => $item['title'],
            'url' => $item['url'],
            'channel' => $item['channel'],
            'channel_url' => $item['channel_url'],
            'thumbnail' => $item['thumbnail'],
        ];
    }

    private static function isConnectionFailure(Throwable $e): bool
    {
        // SQLSTATE classe 08 = falha de conexão; o PDO às vezes só entrega a mensagem.
        return $e instanceof QueryException && (str_starts_with((string) $e->getCode(), '08') || preg_match('/server closed the connection|no connection to the server|SSL connection has been closed|could not connect|connection refused/i', $e->getMessage()) === 1);
    }
}
