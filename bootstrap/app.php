<?php

declare(strict_types=1);

use App\Features\Docs\Http\Controllers\ShowDocsController;
use App\Features\Docs\Http\Controllers\ShowOpenApiController;
use App\Features\Health\Http\Controllers\ShowHealthController;
use App\Features\Health\Http\Controllers\ShowHealthDbController;
use App\Features\Auth\Console\MintToken;
use App\Features\Video\Console\RefreshVideos;
use App\Features\Auth\Http\Middleware\OptionalAuth;
use App\Features\Auth\Http\Middleware\RequireAdmin;
use App\Features\Auth\Http\Middleware\RequireAuth;
use App\Shared\Console\ConfigCheck;
use App\Shared\Http\ErrorRenderer;
use App\Shared\Http\Middleware\AssignRequestId;
use App\Shared\Http\Middleware\SecurityHeaders;
use App\Shared\Http\Middleware\RejectMalformedInput;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        apiPrefix: 'v1',
        then: function (): void {
            Route::get('/health', ShowHealthController::class)->name('health');
            Route::get('/health/db', ShowHealthDbController::class)->name('health.db');
            Route::get('/docs', ShowDocsController::class)->name('docs');
            Route::get('/docs/openapi.yaml', ShowOpenApiController::class)->name('docs.openapi');
        },
    )
    ->withCommands([ConfigCheck::class, MintToken::class, RefreshVideos::class])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->api(append: [RejectMalformedInput::class]);
        // O limiter `writes` usa o dev que o `auth` deixou na requisição: `auth` precisa rodar antes do `throttle` (que é prioritário).
        $middleware->prependToPriorityList(before: ThrottleRequests::class, prepend: RequireAuth::class);
        $middleware->prependToPriorityList(before: ThrottleRequests::class, prepend: RequireAdmin::class);
        $middleware->alias(['auth' => RequireAuth::class, 'auth.optional' => OptionalAuth::class, 'admin' => RequireAdmin::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn(): bool => true);
        $exceptions->render(fn(Throwable $e, Request $request) => (new ErrorRenderer())->render($e, $request));
    })->create();
