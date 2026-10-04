<?php

declare(strict_types=1);

namespace App\Features\Dev\Actions;

use App\Features\Dev\Data\DevView;
use App\Features\Dev\Exceptions\DevNotFound;
use App\Features\Dev\Queries\DevWriter;
use App\Shared\Auth\AuthenticatedDev;
use InvalidArgumentException;

/** Like ou dislike em outro dev (e o desfazer). Idempotente; reagir a si mesmo não grava nada (F5-4). Devolve o dev do token. */
final class ReactToDev
{
    public const TYPES = ['like', 'dislike'];

    public function __construct(
        private readonly DevWriter $devs,
        private readonly ShowProfile $profile,
    ) {}

    /** @throws DevNotFound */
    public function __invoke(AuthenticatedDev $actor, string $targetUsername, string $type, bool $add): DevView
    {
        in_array($type, self::TYPES, true) || throw new InvalidArgumentException("tipo de reação inválido: {$type}");

        $targetId = $this->devs->idOf($targetUsername) ?? throw new DevNotFound("dev {$targetUsername} not found");

        if ($targetId !== $actor->id) {
            $add
                ? $this->devs->addReaction($actor->id, $targetId, $type)
                : $this->devs->removeReaction($actor->id, $targetId, $type);
        }

        return ($this->profile)($actor);
    }
}
