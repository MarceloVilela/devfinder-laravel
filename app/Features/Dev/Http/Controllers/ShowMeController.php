<?php

declare(strict_types=1);

namespace App\Features\Dev\Http\Controllers;

use App\Features\Dev\Http\Resources\DevResource;
use App\Features\Dev\Queries\DevQueries;
use App\Shared\Auth\AuthenticatedDev;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowMeController
{
    public function __construct(private readonly DevQueries $devs) {}

    /** O middleware `auth` já garantiu o Dev (token de Dev inexistente nem chega aqui: 401, D-2). */
    public function __invoke(Request $request): JsonResponse
    {
        $dev = $request->attributes->get(AuthenticatedDev::ATTRIBUTE);

        abort_unless($dev instanceof AuthenticatedDev, 401);

        return response()->json((new DevResource($this->devs->view($dev)))->resolve());
    }
}
