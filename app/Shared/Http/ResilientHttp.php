<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Sleep;

/**
 * Chamada HTTP de saída com retry (F6-8): até 3 tentativas, espera de 0,5 s dobrando, respeitando `Retry-After` (segundos ou data, no
 * máximo 10 s) em erro de conexão e em 429, 502, 503 e 504. Devolve a última resposta (o chamador decide o que fazer com ela) ou relança
 * o último erro de conexão.
 */
final class ResilientHttp
{
    private const RETRY_STATUS = [429, 502, 503, 504];

    private const MAX_RETRY_AFTER_SECONDS = 10;

    /**
     * @param Closure(): Response $request
     *
     * @throws ConnectionException
     */
    public static function send(Closure $request, int $attempts = 3, int $baseMillis = 500): Response
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $response = $request();
            } catch (ConnectionException $e) {
                if ($attempt >= $attempts) {
                    throw $e;
                }

                Sleep::usleep($baseMillis * 1000 * (2 ** ($attempt - 1)));

                continue;
            }

            if ($attempt >= $attempts || ! in_array($response->status(), self::RETRY_STATUS, true)) {
                return $response;
            }

            Sleep::usleep(self::delayMicros($response, $attempt, $baseMillis));
        }
    }

    private static function delayMicros(Response $response, int $attempt, int $baseMillis): int
    {
        $header = $response->header('Retry-After');
        $backoff = $baseMillis * 1000 * (2 ** ($attempt - 1));

        if ($header === '') {
            return $backoff;
        }

        $seconds = ctype_digit($header) ? (int) $header : max(0, CarbonImmutable::parse($header)->getTimestamp() - time());

        return min($seconds, self::MAX_RETRY_AFTER_SECONDS) * 1_000_000;
    }
}
