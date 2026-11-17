<?php

namespace App\Http\Middleware;

use App\Domain\Licensing\Services\BusinessLicenseEntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingIsCompleted
{
    public function __construct(
        private readonly BusinessLicenseEntitlementService $licenseEntitlements,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $businesses = $user->accessibleBusinesses();

        if ($businesses->isEmpty()) {
            return redirect()->route('onboarding.wizard');
        }

        $selectedBusinessId = (int) $request->session()->get('platform.business_id');
        $business = $businesses->firstWhere('id', $selectedBusinessId) ?? $businesses->first();

        if (! $this->licenseEntitlements->businessHasActiveLicense($business)) {
            return redirect()->route('platform.account-license-activation');
        }

        return $next($request);
    }
}
