<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Support\ConfigKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    private static bool $booted = false;

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        if (! self::$booted && ! $this->app->runningInConsole()) {
            self::$booted = true;
            Log::info('boot', [
                'env' => $this->app->environment(),
                'config_present' => $this->app->make(ConfigKeys::class)->present(),
                'config_missing' => $this->app->make(ConfigKeys::class)->missing(),
            ]);
        }
    }
}
