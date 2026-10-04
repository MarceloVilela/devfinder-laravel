<?php

declare(strict_types=1);

namespace App\Features\Dev\Http\Requests;

use App\Shared\Support\GithubLogin;
use Illuminate\Foundation\Http\FormRequest;

final class StoreDevRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['username' => ['required', 'string', 'regex:' . GithubLogin::PATTERN]];
    }

    public function username(): string
    {
        $username = $this->validated('username');

        return is_string($username) ? $username : '';
    }
}
