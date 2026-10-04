<?php

declare(strict_types=1);

namespace App\Features\Dev\Actions;

use App\Features\Dev\Data\DevView;
use App\Features\Dev\Queries\DevQueries;
use App\Shared\Auth\AuthenticatedDev;
use InvalidArgumentException;

/** `GET /likes/devs` e `/dislikes/devs`: devs ativos que o dev do token marcou, sem ele mesmo. 3 queries. */
final class ListReactedDevs
{
    public function __construct(private readonly DevQueries $devs) {}

    /** @return list<DevView> */
    public function __invoke(AuthenticatedDev $actor, string $type): array
    {
        in_array($type, ReactToDev::TYPES, true) || throw new InvalidArgumentException("tipo de reação inválido: {$type}");

        return $this->devs->reactedBy($actor->id, $type);
    }
}
