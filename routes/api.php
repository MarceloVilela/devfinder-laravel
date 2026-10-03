<?php

declare(strict_types=1);

use App\Features\Channel\Http\Controllers\ListChannelsController;
use App\Features\Channel\Http\Controllers\ShowChannelController;
use App\Features\Description\Http\Controllers\ShowCategoryDescriptionController;
use App\Features\Description\Http\Controllers\ShowFeedDescriptionController;
use App\Features\Dev\Http\Controllers\ListDevsController;
use App\Features\Dev\Http\Controllers\ShowDevController;
use App\Features\Info\Http\Controllers\ShowAppInfoController;
use App\Features\Search\Http\Controllers\SearchCatalogController;
use App\Features\Video\Http\Controllers\ListChannelFeedController;
use App\Features\Video\Http\Controllers\ListTrendingController;
use App\Features\Video\Http\Controllers\ShowVideoController;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowAppInfoController::class);

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
