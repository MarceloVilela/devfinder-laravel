<?php

declare(strict_types=1);

namespace App\Features\Health\Http\Controllers;

use Illuminate\Http\JsonResponse;

final class ShowHealthController
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }
}
