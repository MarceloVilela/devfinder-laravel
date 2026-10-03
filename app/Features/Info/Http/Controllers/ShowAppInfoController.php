<?php

declare(strict_types=1);

namespace App\Features\Info\Http\Controllers;

use Illuminate\Http\JsonResponse;

final class ShowAppInfoController
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['appname' => config('devfinder.appname')]);
    }
}
