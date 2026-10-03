<?php

declare(strict_types=1);

namespace App\Features\Health\Exceptions;

use App\Shared\Exceptions\ServiceUnavailable;

final class DatabaseUnavailable extends ServiceUnavailable {}
