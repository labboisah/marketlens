<?php

namespace App\Http\Controllers;

use App\Domain\Licensing\Services\BusinessLicenseEntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnboardingCompletedController extends Controller
{
    public function __invoke(Request $request, BusinessLicenseEntitlementService $licenseEntitlements): View|RedirectResponse
    {
        $user = auth()->user();
        $businesses = $user?->accessibleBusinesses() ?? collect();

        if ($businesses->isNotEmpty()) {
            $selectedBusinessId = (int) $request->session()->get('platform.business_id');
            $business = $businesses->firstWhere('id', $selectedBusinessId) ?? $businesses->first();

            if (! $licenseEntitlements->businessHasActiveLicense($business)) {
                return redirect()->route('platform.account-license-activation');
            }
        }

        return view('onboarding.completed');
    }
}
