<?php

declare(strict_types=1);

namespace App\Features\Description\Http\Controllers;

use App\Features\Description\Actions\BuildFeedDescription;
use Illuminate\Http\Response;

final class ShowFeedDescriptionController
{
    public function __invoke(BuildFeedDescription $feed): Response
    {
        return response($feed(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
