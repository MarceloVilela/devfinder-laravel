<?php

declare(strict_types=1);

namespace App\Features\Video\Console;

use App\Features\Video\Actions\IngestVideos;
use App\Features\Video\Exceptions\JsonBinUnavailable;
use App\Features\Video\Integrations\JsonBinClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ingestão agendada (F6-7): busca os candidatos no JSONBin (ou numa fixture) e chama a mesma Action da rota `POST /video/refresh`.
 * O bin usa `channel_name`; o contrato usa `channel`: a troca acontece aqui, na borda.
 */
final class RefreshVideos extends Command
{
    protected $signature = 'video:refresh {--fixture= : arquivo JSON no formato do bin, no lugar do JSONBin}';

    protected $description = 'Ingestão em lote de vídeos a partir do bin do JSONBin (ou de uma fixture)';

    public function handle(JsonBinClient $bin, IngestVideos $ingest): int
    {
        try {
            $record = $this->source($bin);
        } catch (JsonBinUnavailable $e) {
            Log::error('video:refresh: fonte indisponível', ['cause' => $e->getMessage()]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($record === null) {
            $this->warn('JSONBIN_API_KEY e JSONBIN_ID_SUBS não configurados e sem --fixture: nada a fazer.');

            return self::SUCCESS;
        }

        $this->line('JSONBin: ' . count($record) . ' candidato(s) encontrado(s).');

        try {
            $result = $ingest(array_map(self::candidate(...), $record));
        } catch (Throwable $e) {
            Log::error('video:refresh: lote abortado', ['cause' => $e::class . ': ' . $e->getMessage()]);
            $this->error('Lote abortado: ' . $e->getMessage());

            return self::FAILURE;
        }

        $added = count($result->videosAdded);
        $found = count($result->videosFounded);
        $errors = count($result->errors);
        $this->info("Adicionados: {$added} | Já existiam: {$found} | Erros: {$errors}");

        foreach ($result->errors as $error) {
            $this->line('  - ' . ($error['errorMessage'] ?? 'erro'));
        }

        Log::info('video:refresh', ['candidates' => count($record), 'added' => $added, 'found' => $found, 'errors' => $errors]);

        return self::SUCCESS;
    }

    /** @return list<mixed>|null `null` = sem fonte configurada */
    private function source(JsonBinClient $bin): ?array
    {
        $fixture = $this->option('fixture');

        if (is_string($fixture) && $fixture !== '') {
            $decoded = is_file($fixture) ? json_decode((string) file_get_contents($fixture), true) : null;
            $record = is_array($decoded) ? ($decoded['record'] ?? $decoded) : null;

            return is_array($record) && array_is_list($record) ? $record : throw new JsonBinUnavailable("fixture inválida: {$fixture}");
        }

        return $bin->configured() ? $bin->candidates() : null;
    }

    /** @return array<string, mixed> */
    private static function candidate(mixed $item): array
    {
        $item = is_array($item) ? $item : [];

        return [
            'title' => $item['title'] ?? '',
            'url' => $item['url'] ?? '',
            'channel' => $item['channel_name'] ?? '',
            'channel_url' => $item['channel_url'] ?? '',
            'thumbnail' => $item['thumbnail'] ?? '',
        ];
    }
}
