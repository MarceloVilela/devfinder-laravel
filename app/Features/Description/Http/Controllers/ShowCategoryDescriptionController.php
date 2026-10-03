<?php

declare(strict_types=1);

namespace App\Features\Description\Http\Controllers;

use App\Features\Description\Actions\BuildCategoryDescriptions;
use Illuminate\Http\Response;

final class ShowCategoryDescriptionController
{
    public function __invoke(BuildCategoryDescriptions $categories): Response
    {
        return response($categories(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
