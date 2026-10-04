<?php

declare(strict_types=1);

namespace App\Features\Video\Integrations;

use App\Features\Video\Exceptions\JsonBinUnavailable;
use App\Shared\Http\ResilientHttp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class HttpJsonBinClient implements JsonBinClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $binId,
        private readonly int $timeoutSeconds,
    ) {}

    public function configured(): bool
    {
        return $this->apiKey !== '' && $this->binId !== '';
    }

    public function candidates(): array
    {
        $this->configured() || throw new JsonBinUnavailable('JSONBin sem credenciais');

        try {
            // A chave só vai no cabeçalho `X-Master-Key`; nenhuma mensagem de erro ou log a repete.
            $response = ResilientHttp::send(fn() => Http::timeout($this->timeoutSeconds)
                ->withHeaders(['X-Master-Key' => $this->apiKey, 'Accept' => 'application/json'])
                ->get('https://api.jsonbin.io/v3/b/' . rawurlencode($this->binId)));
        } catch (ConnectionException $e) {
            throw new JsonBinUnavailable('JSONBin inacessível depois dos retries', $e);
        }

        if (! $response->ok()) {
            throw new JsonBinUnavailable("JSONBin respondeu {$response->status()}");
        }

        $record = $response->json('record');

        return is_array($record) && array_is_list($record)
            ? $record
            : throw new JsonBinUnavailable('JSONBin devolveu um formato inesperado (esperava record: lista)');
    }
}
