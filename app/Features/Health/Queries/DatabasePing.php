<?php

declare(strict_types=1);

namespace App\Features\Health\Queries;

use App\Features\Health\Exceptions\DatabaseUnavailable;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DatabasePing
{
    /** @throws DatabaseUnavailable */
    public function check(): void
    {
        try {
            DB::select('select 1');
        } catch (Throwable $e) {
            throw new DatabaseUnavailable('database ping failed', $e);
        }
    }
}
