<?php

declare(strict_types=1);

namespace App\Features\Docs\Http\Controllers;

use Illuminate\Http\Response;

final class ShowDocsController
{
    public function __invoke(): Response
    {
        return response(view('docs'), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
