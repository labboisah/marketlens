<?php

namespace App\Http\Controllers;

use App\Domain\Licensing\Models\BusinessLicenseAssociation;
use App\Domain\Licensing\Services\KernelBridgeLicenseSyncService;
use App\Domain\Tenancy\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class AccountLicenseActivationController extends Controller
{
    public function begin(Request $request, KernelBridgeLicenseSyncService $licenses): JsonResponse
    {
        $validated = $request->validate([
            'activation_code' => ['required', 'string', 'max:255'],
        ]);
        $license = $licenses->redeemActivationCode($this->businessFor($request), $validated['activation_code']);

        return response()->json([
            'linkId' => data_get($license->provider_metadata, 'account_link_uuid'),
            'status' => data_get($license->provider_metadata, 'account_link_status'),
        ]);
    }

    public function status(
        Request $request,
        string $linkUuid,
        KernelBridgeLicenseSyncService $licenses,
    ): JsonResponse {
        $business = $this->businessFor($request);
        $this->licenseForLink($business, $linkUuid);
        $license = $licenses->refreshAccountLink($business);

        return response()->json([
            'status' => data_get($license->provider_metadata, 'account_link_status'),
        ]);
    }

    public function complete(
        Request $request,
        string $linkUuid,
        KernelBridgeLicenseSyncService $licenses,
    ): JsonResponse {
        $business = $this->businessFor($request);
        $this->licenseForLink($business, $linkUuid);
        $license = $licenses->refreshAccountLink($business);

        if (data_get($license->provider_metadata, 'account_link_uuid') !== $linkUuid
            || data_get($license->provider_metadata, 'account_link_status') !== 'approved'
            || $license->status !== 'active') {
            return response()->json([
                'message' => 'The KernelBridge account link is not approved with an active license yet.',
            ], 409);
        }

        $redirectRoute = config('kernelbridge.platform_link_redirect_route');
        abort_unless(is_string($redirectRoute) && Route::has($redirectRoute), 500, 'The post-activation destination route is not configured.');

        return response()->json(['redirectTo' => route($redirectRoute)]);
    }

    public function unlink(
        Request $request,
        string $linkUuid,
        KernelBridgeLicenseSyncService $licenses,
    ): JsonResponse {
        $business = $this->businessFor($request);
        $this->licenseForLink($business, $linkUuid);
        $licenses->unlinkAccount($business);

        return response()->json(['success' => true]);
    }

    private function businessFor(Request $request): Business
    {
        $user = $request->user();
        abort_unless($user, 403);

        $selectedBusinessId = (int) $request->session()->get('platform.business_id');
        $business = $user->ownedBusinesses()->whereKey($selectedBusinessId)->first()
            ?? $user->ownedBusinesses()->first();

        abort_unless($business, 403);

        return $business;
    }

    private function licenseForLink(Business $business, string $linkUuid): BusinessLicenseAssociation
    {
        $license = $business->kernelbridgeLicense()->first();
        $storedLinkUuid = data_get($license?->provider_metadata, 'account_link_uuid');

        if (! $license || ! is_string($storedLinkUuid) || ! hash_equals($storedLinkUuid, $linkUuid)) {
            abort(404);
        }

        return $license;
    }
}
