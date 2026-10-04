<?php

declare(strict_types=1);

namespace App\Features\Channel\Actions;

use App\Features\Channel\Exceptions\ChannelNotFound;
use App\Features\Channel\Queries\ChannelWriter;
use App\Features\Dev\Actions\ShowProfile;
use App\Features\Dev\Data\DevView;
use App\Shared\Auth\AuthenticatedDev;
use InvalidArgumentException;

/** Follow ou ignore de canal (e o desfazer), idempotente; o canal vem por nome exato. Devolve o dev do token (F5-3, F5-5). */
final class ReactToChannel
{
    public const TYPES = ['follow', 'ignore'];

    public function __construct(
        private readonly ChannelWriter $channels,
        private readonly ShowProfile $profile,
    ) {}

    /** @throws ChannelNotFound */
    public function __invoke(AuthenticatedDev $actor, string $channelName, string $type, bool $add): DevView
    {
        in_array($type, self::TYPES, true) || throw new InvalidArgumentException("tipo de reação inválido: {$type}");

        $channelId = $this->channels->idByName($channelName) ?? throw new ChannelNotFound("channel {$channelName} not found");

        $add
            ? $this->channels->addReaction($actor->id, $channelId, $type)
            : $this->channels->removeReaction($actor->id, $channelId, $type);

        return ($this->profile)($actor);
    }
}
