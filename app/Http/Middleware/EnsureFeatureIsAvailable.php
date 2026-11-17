<?php

namespace App\Http\Middleware;

use App\Domain\Licensing\Services\BusinessLicenseEntitlementService;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BusinessContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureIsAvailable
{
    public function __construct(
        private readonly BusinessContext $businessContext,
        private readonly BusinessLicenseEntitlementService $licenseEntitlements,
    ) {}

    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        $business = $request->attributes->get('platform.business')
            ?: Business::query()->find($this->businessContext->id());

        if (! $business || ! $this->licenseEntitlements->allowsAny($business, $features)) {
            return redirect()->route('platform.permission-denied')
                ->with('module_unavailable', 'Your MarketLens license does not include this feature.');
        }

        return $next($request);
    }
}
