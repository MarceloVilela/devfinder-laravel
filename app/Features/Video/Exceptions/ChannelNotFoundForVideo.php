<?php

declare(strict_types=1);

namespace App\Features\Video\Exceptions;

use App\Shared\Exceptions\ApiException;

/** Canal do vídeo não achado: 400 com o `errorMessage` e os campos enviados (formato do contrato, `erros-v1.md`). */
final class ChannelNotFoundForVideo extends ApiException
{
    public function __construct(
        private readonly string $title,
        private readonly string $url,
        private readonly string $channel,
        private readonly string $channelUrl,
        private readonly string $thumbnail,
    ) {
        parent::__construct("channel({$channel}) not found, for: {$title}");
    }

    public function status(): int
    {
        return 400;
    }

    public function body(): array
    {
        return [
            'errorMessage' => $this->getMessage(),
            'title' => $this->title,
            'url' => $this->url,
            'channel' => $this->channel,
            'channel_url' => $this->channelUrl,
            'thumbnail' => $this->thumbnail,
        ];
    }
}
