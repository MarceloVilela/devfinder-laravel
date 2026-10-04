<?php

declare(strict_types=1);

namespace App\Features\Dev\Actions;

use App\Features\Dev\Data\DevView;
use App\Features\Dev\Queries\DevQueries;
use App\Shared\Auth\AuthenticatedDev;

/** O dev do token como o contrato o expõe, com as reações lidas agora (as respostas de escrita devolvem isto). 2 queries. */
final class ShowProfile
{
    public function __construct(private readonly DevQueries $devs) {}

    public function __invoke(AuthenticatedDev $actor): DevView
    {
        return $this->devs->view($actor);
    }
}
