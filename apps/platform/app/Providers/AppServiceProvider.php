<?php

namespace App\Providers;

use App\Domain\Tenancy\Services\BranchContext;
use App\Domain\Tenancy\Services\BusinessContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BusinessContext::class);
        $this->app->singleton(BranchContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('device', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('sync', fn (Request $request) => Limit::perMinute((int) config('marketlens.sync_rate_limit', 120))->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('monitoring', fn (Request $request) => Limit::perMinute(90)->by($request->user()?->id ?: $request->ip()));
    }
}
