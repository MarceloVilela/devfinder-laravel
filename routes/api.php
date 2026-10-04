<?php

declare(strict_types=1);

use App\Features\Auth\Http\Controllers\HandleGithubCallbackController;
use App\Features\Auth\Http\Controllers\LogoutController;
use App\Features\Auth\Http\Controllers\RedirectToGithubController;
use App\Features\Channel\Http\Controllers\AddChannelReactionController;
use App\Features\Channel\Http\Controllers\ListChannelsController;
use App\Features\Channel\Http\Controllers\RemoveChannelReactionController;
use App\Features\Channel\Http\Controllers\ShowChannelController;
use App\Features\Channel\Http\Controllers\StoreChannelController;
use App\Features\Description\Http\Controllers\ShowCategoryDescriptionController;
use App\Features\Description\Http\Controllers\ShowFeedDescriptionController;
use App\Features\Dev\Http\Controllers\AddDevReactionController;
use App\Features\Dev\Http\Controllers\ListDevsController;
use App\Features\Dev\Http\Controllers\ListReactedDevsController;
use App\Features\Dev\Http\Controllers\RemoveDevReactionController;
use App\Features\Dev\Http\Controllers\ShowDevController;
use App\Features\Dev\Http\Controllers\ShowMeController;
use App\Features\Dev\Http\Controllers\StoreDevController;
use App\Features\Info\Http\Controllers\ShowAppInfoController;
use App\Features\Search\Http\Controllers\SearchCatalogController;
use App\Features\Video\Http\Controllers\ListChannelFeedController;
use App\Features\Video\Http\Controllers\ListSubscriptionsController;
use App\Features\Video\Http\Controllers\ListTrendingController;
use App\Features\Video\Http\Controllers\ShowVideoController;
use App\Features\Video\Http\Controllers\StoreVideoController;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowAppInfoController::class);

// Login (F4-10: o único lugar com rate limiting; cada pedido limitado acorda o Neon, ADR 0011).
Route::middleware('throttle:auth')->group(function (): void {
    Route::get('/auth/github', RedirectToGithubController::class);
    Route::get('/auth/github/callback', HandleGithubCallbackController::class);
});

Route::post('/auth/logout', LogoutController::class);
Route::get('/me', ShowMeController::class)->middleware('auth');

// Escrita e relacionamento (Fase 5). Todas exigem token. Criação com limite por dev (F5-12, ADR 0011); reações e leituras sem limite.
Route::middleware('auth')->group(function (): void {
    // `POST /devs` fica para qualquer dev autenticado; canal e vídeo só para ADMIN (RBAC, F5-15), com limite por dev (F5-12).
    Route::post('/devs', StoreDevController::class)->middleware('throttle:writes');

    Route::middleware(['admin', 'throttle:writes'])->group(function (): void {
        Route::post('/channels', StoreChannelController::class);
        Route::post('/video', StoreVideoController::class);
    });

    // O tipo da reação vem do `defaults`: um controller de adicionar e um de remover por entidade (F5-3).
    foreach (['likes' => 'like', 'dislikes' => 'dislike'] as $prefix => $type) {
        Route::post("/{$prefix}/devs/{username}", AddDevReactionController::class)->defaults('type', $type);
        Route::delete("/{$prefix}/devs/{username}", RemoveDevReactionController::class)->defaults('type', $type);
        Route::get("/{$prefix}/devs", ListReactedDevsController::class)->defaults('type', $type);
    }

    foreach (['likes' => 'follow', 'dislikes' => 'ignore'] as $prefix => $type) {
        // `{username}` é o nome do canal (o contrato chama assim; o v1 e o original usam `channels.name`).
        // `.+`: o nome do canal pode ter `/` (F3-5); sem isso o nome com barra daria 404 em vez de seguir o canal.
        Route::post("/{$prefix}/channels/{username}", AddChannelReactionController::class)->where('username', '.+')->defaults('type', $type);
        Route::delete("/{$prefix}/channels/{username}", RemoveChannelReactionController::class)->where('username', '.+')->defaults('type', $type);
    }

    Route::get('/feed/subscriptions', ListSubscriptionsController::class);
});

// `x-auth: optional` no contrato: token válido personaliza, qualquer outra coisa segue anônima.
Route::middleware('auth.optional')->group(function (): void {
    Route::get('/devs', ListDevsController::class);
    Route::get('/devs/{username}', ShowDevController::class);

    Route::get('/channels', ListChannelsController::class);
    // `.+`: o nome ou o link do canal pode trazer `/` (`%2F`), que o v1 não conseguia receber (F3-5).
    Route::get('/channels/{searchQuery}', ShowChannelController::class)->where('searchQuery', '.+');

    Route::get('/feed/trending', ListTrendingController::class);
    Route::get('/feed/channel', ListChannelFeedController::class);
    Route::get('/video/{idYoutubeWatch}', ShowVideoController::class);

    Route::get('/description/feed', ShowFeedDescriptionController::class);
    Route::get('/description/category', ShowCategoryDescriptionController::class);

    Route::get('/search', SearchCatalogController::class);
});
