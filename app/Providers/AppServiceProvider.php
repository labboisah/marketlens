<?php

namespace App\Providers;

use App\Domain\Billing\Contracts\PaymentGateway;
use App\Domain\Billing\Gateways\NullPaymentGateway;
use App\Domain\Licensing\Services\KernelBridgePlatformCredentialProvider;
use App\Domain\Modules\Services\ModuleAccessService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BranchContext;
use App\Domain\Tenancy\Services\BusinessContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use KernelBridge\LicensingClient\Platform\PlatformCredentialProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BusinessContext::class);
        $this->app->singleton(BranchContext::class);
        $this->app->bind(PaymentGateway::class, NullPaymentGateway::class);
        $this->app->bind(PlatformCredentialProvider::class, KernelBridgePlatformCredentialProvider::class);

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        config([
            'kernelbridge-licensing.product_code' => config('kernelbridge.product_code', 'MARKETLENS'),
            'kernelbridge-licensing.connect_timeout_seconds' => config('kernelbridge.connect_timeout_seconds', 3),
            'kernelbridge-licensing.timeout_seconds' => config('kernelbridge.timeout_seconds', 10),
        ]);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(3)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('device', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('sync', fn (Request $request) => Limit::perMinute((int) config('marketlens.sync_rate_limit', 120))->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('monitoring', fn (Request $request) => Limit::perMinute(90)->by($request->user()?->id ?: $request->ip()));

        Blade::if('moduleAvailable', fn (Business $business, string $module, ?Branch $branch = null): bool => app(ModuleAccessService::class)->available($business, $module, $branch));
        Blade::if('moduleUnavailable', fn (Business $business, string $module, ?Branch $branch = null): bool => ! app(ModuleAccessService::class)->available($business, $module, $branch));

        $this->registerSlowQueryLogging();
    }

    private function registerSlowQueryLogging(): void
    {
        if (! config('performance.monitoring.slow_query.enabled')) {
            return;
        }

        DB::listen(function (QueryExecuted $query): void {
            $threshold = (int) config('performance.monitoring.slow_query.threshold_ms', 250);

            if ($query->time < $threshold) {
                return;
            }

            Log::warning('Slow database query detected', [
                'time_ms' => $query->time,
                'connection' => $query->connectionName,
                'sql_shape' => preg_replace('/\s+/', ' ', $query->sql),
                'bindings' => '[redacted]',
                'path' => request() ? '/'.ltrim(request()->path(), '/') : null,
            ]);
        });
    }
}
