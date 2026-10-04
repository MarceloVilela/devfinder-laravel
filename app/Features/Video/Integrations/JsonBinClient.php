<?php

declare(strict_types=1);

namespace App\Features\Video\Integrations;

use App\Features\Video\Exceptions\JsonBinUnavailable;

/** Fonte dos candidatos da ingestão agendada (o mesmo bin do `devfinder-api`). Atrás de interface: os testes usam `Http::fake`. */
interface JsonBinClient
{
    /** `JSONBIN_API_KEY` e `JSONBIN_ID_SUBS` presentes. */
    public function configured(): bool;

    /**
     * Itens do bin, no formato do bin (`channel_name`, não `channel`).
     *
     * @return list<mixed>
     *
     * @throws JsonBinUnavailable
     */
    public function candidates(): array;
}
