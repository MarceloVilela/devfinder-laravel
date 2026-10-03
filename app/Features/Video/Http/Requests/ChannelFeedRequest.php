<?php

declare(strict_types=1);

namespace App\Features\Video\Http\Requests;

use App\Shared\Http\Requests\PageRequest;

final class ChannelFeedRequest extends PageRequest
{
    /** `channel_name` ausente ou vazio resolve como canal inexistente (lista vazia), como no v1. */
    public function channelName(): ?string
    {
        $name = $this->query('channel_name');

        return is_string($name) && $name !== '' ? $name : null;
    }
}
