<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Github\GithubClient;
use App\Shared\Github\HttpGithubClient;
use App\Features\Auth\Support\SessionCookie;
use App\Features\Video\Integrations\HttpJsonBinClient;
use App\Features\Video\Integrations\JsonBinClient;
use App\Features\Auth\Support\TokenCodec;
use App\Shared\Auth\AuthenticatedDev;
use App\Shared\Support\ConfigKeys;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    private static bool $booted = false;

    public function register(): void
    {
        $this->app->singleton(TokenCodec::class, fn(): TokenCodec => new TokenCodec(
            self::text(config('devfinder.auth.jwt_secret')),
            self::number(config('devfinder.auth.jwt_ttl_seconds')),
        ));

        $this->app->bind(JsonBinClient::class, fn(): JsonBinClient => new HttpJsonBinClient(
            self::text(config('devfinder.ingestion.jsonbin.api_key')),
            self::text(config('devfinder.ingestion.jsonbin.bin_id')),
            self::number(config('devfinder.ingestion.jsonbin.timeout_seconds')),
        ));

        $this->app->bind(SessionCookie::class, fn(): SessionCookie => SessionCookie::fromConfig());

        $this->app->bind(GithubClient::class, fn(): GithubClient => new HttpGithubClient(
            self::text(config('devfinder.auth.github.client_id')),
            self::text(config('devfinder.auth.github.client_secret')),
            self::text(config('devfinder.auth.github.redirect_uri')),
            self::number(config('devfinder.auth.github.timeout_seconds')),
        ));
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        // F4-10: só as rotas de login; o limite vem de config e o store é o `cache.limiter` (ADR 0011).
        RateLimiter::for('auth', fn(Request $request): Limit => Limit::perMinute(self::number(config('devfinder.auth.rate_limit_per_minute')))
            ->by((string) $request->ip()));

        // F5-12: escrita sensível, por dev (o `auth` roda antes e deixa o dev na requisição); sem token cai no IP.
        RateLimiter::for('writes', fn(Request $request): Limit => Limit::perMinute(self::number(config('devfinder.auth.writes_per_minute')))
            ->by($request->attributes->get(AuthenticatedDev::ATTRIBUTE) instanceof AuthenticatedDev ? $request->attributes->get(AuthenticatedDev::ATTRIBUTE)->id : (string) $request->ip()));

        if (! self::$booted && ! $this->app->runningInConsole()) {
            self::$booted = true;
            Log::info('boot', [
                'env' => $this->app->environment(),
                'config_present' => $this->app->make(ConfigKeys::class)->present(),
                'config_missing' => $this->app->make(ConfigKeys::class)->missing(),
            ]);
        }
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function number(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }
}
