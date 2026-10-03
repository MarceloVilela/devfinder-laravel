<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Illuminate\Http\Response;

/** Corpo JSON `null` (`response()->json(null)` viraria `{}`): "não encontrado" é 200 com `null` no contrato (F3-2). */
final class JsonNull
{
    public static function response(): Response
    {
        return response('null', 200, ['Content-Type' => 'application/json']);
    }
}
