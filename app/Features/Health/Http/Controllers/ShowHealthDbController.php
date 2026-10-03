<?php

declare(strict_types=1);

namespace App\Features\Health\Http\Controllers;

use App\Features\Health\Queries\DatabasePing;
use Illuminate\Http\JsonResponse;

final class ShowHealthDbController
{
    public function __construct(private readonly DatabasePing $ping) {}

    public function __invoke(): JsonResponse
    {
        $this->ping->check();

        return response()->json(['status' => 'ok', 'database' => 'up']);
    }
}
