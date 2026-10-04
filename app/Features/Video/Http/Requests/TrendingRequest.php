<?php

declare(strict_types=1);

namespace App\Features\Video\Http\Requests;

use App\Shared\Http\Requests\PageRequest;

final class TrendingRequest extends PageRequest
{
    /** `?user=<username>`: identifica o dev sem token, como no v1 e no original (F4-9). */
    public function username(): ?string
    {
        $user = $this->query('user');

        return is_string($user) && $user !== '' ? $user : null;
    }
}
