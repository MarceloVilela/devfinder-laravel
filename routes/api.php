<?php

declare(strict_types=1);

use App\Features\Info\Http\Controllers\ShowAppInfoController;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowAppInfoController::class);
