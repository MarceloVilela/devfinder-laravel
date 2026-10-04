<?php

declare(strict_types=1);

namespace App\Features\Video\Http\Requests;

use App\Features\Video\Data\StoreVideoData;
use App\Features\Video\Support\YoutubeUrl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class StoreVideoRequest extends FormRequest
{
    /** @return array<string, list<string|Closure>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:500'],
            'url' => ['required', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || YoutubeUrl::idOf(YoutubeUrl::withoutTracking($value)) === null) {
                    $fail('A url precisa trazer o id do vídeo em v= (1 a 20 caracteres: letras, números, _ e -).');
                }
            }],
            'channel' => ['required', 'string', 'max:255'],
            'channel_url' => ['required', 'string', 'max:500'],
            'thumbnail' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function video(): StoreVideoData
    {
        $v = $this->validated();

        return new StoreVideoData(
            title: self::text($v['title'] ?? null),
            url: self::text($v['url'] ?? null),
            channel: self::text($v['channel'] ?? null),
            channelUrl: self::text($v['channel_url'] ?? null),
            thumbnail: self::text($v['thumbnail'] ?? null),
        );
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
