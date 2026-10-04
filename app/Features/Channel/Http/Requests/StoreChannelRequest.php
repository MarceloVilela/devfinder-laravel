<?php

declare(strict_types=1);

namespace App\Features\Channel\Http\Requests;

use App\Features\Channel\Data\StoreChannelData;
use App\Shared\Support\GithubLogin;
use Illuminate\Foundation\Http\FormRequest;

final class StoreChannelRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'link' => ['required', 'string', 'max:500'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:20000'],
            'avatar' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['nullable', 'string', 'max:100'],
            'userGithub' => ['nullable', 'string', 'regex:' . GithubLogin::PATTERN],
        ];
    }

    public function channel(): StoreChannelData
    {
        $v = $this->validated();

        return new StoreChannelData(
            link: self::text($v['link'] ?? null),
            title: self::text($v['title'] ?? null),
            description: self::nullableText($v['description'] ?? null),
            category: self::text($v['category'] ?? null),
            tags: array_values(array_filter(
                is_array($v['tags'] ?? null) ? $v['tags'] : [],
                static fn(mixed $tag): bool => is_string($tag) && $tag !== '',
            )),
            userGithub: self::nullableText($v['userGithub'] ?? null),
            avatar: self::nullableText($v['avatar'] ?? null),
        );
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function nullableText(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
