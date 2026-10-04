<?php

declare(strict_types=1);

namespace App\Features\Video\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class IngestVideosRequest extends FormRequest
{
    public const MAX_CANDIDATES = 200;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        // `record` ausente ou vazio é 200 com listas vazias (paridade com o v1). O formato de cada item é problema da Action: vira item de `errors`.
        return ['record' => ['nullable', 'array', 'max:' . self::MAX_CANDIDATES]];
    }

    /** @return list<mixed> */
    public function candidates(): array
    {
        $record = $this->validated('record');

        return is_array($record) ? array_values($record) : [];
    }
}
