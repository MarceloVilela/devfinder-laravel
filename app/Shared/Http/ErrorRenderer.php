<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Único ponto que monta corpo de erro (ADR 0008). Preserva os formatos públicos do contrato:
 * `{error}` e `{errorMessage, ...}`; nunca devolve stack trace nem a causa de infraestrutura (o report do Laravel a grava no log, com a cadeia `previous`).
 */
final class ErrorRenderer
{
    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        if ($e instanceof ApiException) {
            return response()->json($e->body(), $e->status());
        }

        if ($e instanceof ValidationException) {
            return response()->json(['error' => 'Validation failed.', 'errors' => $e->errors()], 422);
        }

        if ($e instanceof NotFoundHttpException) {
            return response()->json(['error' => 'Not found.'], 404);
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return response()->json(['error' => 'Method not allowed.'], 405, $e->getHeaders());
        }

        if ($e instanceof TooManyRequestsHttpException) {
            return response()->json(['error' => 'Too many requests.'], 429, $e->getHeaders());
        }

        if ($e instanceof HttpExceptionInterface) {
            return response()->json(['error' => 'Request failed.'], $e->getStatusCode(), $e->getHeaders());
        }

        if (config('app.debug') === true) {
            return null;
        }

        return response()->json(['error' => 'Internal server error.'], 500);
    }
}
