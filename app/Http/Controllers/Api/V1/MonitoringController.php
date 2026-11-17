<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Modules\Services\ModuleAccessService;
use App\Domain\Synchronization\Services\SynchronizedOperationalData;
use App\Domain\Tenancy\Models\Business;
use App\Enums\BusinessModule;
use App\Enums\BranchOperatingMode;
use App\Exceptions\ModuleUnavailableException;
use App\Exceptions\OperatingModeUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Monitoring\DashboardRequest;
use App\Http\Resources\Api\SuccessResource;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function dashboard(DashboardRequest $request): SuccessResource
    {
        return new SuccessResource([
            'filters' => $request->validated(),
            'sales_summary' => null,
            'stock_alerts' => [],
            'branches' => [],
            'last_synchronization' => null,
        ]);
    }

    public function services(Request $request, ModuleAccessService $moduleAccess, SynchronizedOperationalData $synchronizedData): SuccessResource
    {
        [$business, $branch] = $this->businessAndBranch($request);
        $access = $moduleAccess->resolve($business, BusinessModule::Services, $branch);

        if (! $access->available) {
            throw new ModuleUnavailableException($access);
        }

        if ($branch && ! in_array(BranchOperatingMode::Services->value, $branch->operating_modes ?? [], true)) {
            throw new OperatingModeUnavailableException(BranchOperatingMode::Services);
        }

        return new SuccessResource([
            'module_access' => $access->toArray(),
            ...$synchronizedData->services($business->id, $branch?->id),
        ]);
    }

    public function shareholders(Request $request, ModuleAccessService $moduleAccess, SynchronizedOperationalData $synchronizedData): SuccessResource
    {
        [$business, $branch] = $this->businessAndBranch($request);
        $access = $moduleAccess->resolve($business, BusinessModule::Shareholders, $branch);

        if (! $access->available) {
            throw new ModuleUnavailableException($access);
        }

        return new SuccessResource([
            'module_access' => $access->toArray(),
            ...$synchronizedData->shareholders($business->id, $branch?->id),
        ]);
    }

    public function shareholder(Request $request, string $uuid, ModuleAccessService $moduleAccess, SynchronizedOperationalData $synchronizedData): SuccessResource
    {
        [$business, $branch] = $this->businessAndBranch($request);
        $access = $moduleAccess->resolve($business, BusinessModule::Shareholders, $branch);

        if (! $access->available) {
            throw new ModuleUnavailableException($access);
        }

        $shareholder = $synchronizedData->shareholders($business->id, $branch?->id)['shareholders']
            ->first(fn (array $record): bool => ($record['uuid'] ?? $record['_record_uuid']) === $uuid);

        abort_unless($shareholder, 404);

        return new SuccessResource(['shareholder' => $shareholder]);
    }

    public function ownership(Request $request, ModuleAccessService $moduleAccess, SynchronizedOperationalData $synchronizedData): SuccessResource
    {
        return $this->shareholders($request, $moduleAccess, $synchronizedData);
    }

    public function profitPeriods(): SuccessResource
    {
        return new SuccessResource(['records' => []]);
    }

    public function profitDistributions(): SuccessResource
    {
        return new SuccessResource(['records' => []]);
    }

    public function branches(Request $request): SuccessResource
    {
        $businesses = Business::query()
            ->with('branches')
            ->where(function ($query) use ($request): void {
                $query->whereHas('owners', fn ($ownerQuery) => $ownerQuery->whereKey($request->user()->id))
                    ->orWhereHas('monitoringUsers', fn ($monitoringQuery) => $monitoringQuery
                        ->where('user_id', $request->user()->id)
                        ->where('status', 'active')
                        ->whereNull('suspended_at')
                        ->whereNull('revoked_at'));
            })
            ->get();

        $branches = $businesses->flatMap(fn (Business $business) => $business->branches
            ->filter(fn ($branch): bool => $request->user()->canAccessBranch($business->id, $branch->id))
            ->map(fn ($branch): array => [
                'business_uuid' => $business->uuid,
                'business_name' => $business->name,
                'branch_uuid' => $branch->uuid,
                'branch_name' => $branch->name,
                'status' => $branch->status?->value,
                'operating_modes' => $branch->operating_modes ?? ['sales'],
            ]))
            ->values();

        return new SuccessResource(['branches' => $branches]);
    }

    /** @return array{0: Business, 1: mixed} */
    private function businessAndBranch(Request $request): array
    {
        $business = Business::query()
            ->with(['branches', 'currentSubscription.plan.features'])
            ->when($request->query('business_uuid'), fn ($query, string $uuid) => $query->where('uuid', $uuid))
            ->where(function ($query) use ($request): void {
                $query->whereHas('owners', fn ($ownerQuery) => $ownerQuery->whereKey($request->user()->id))
                    ->orWhereHas('monitoringUsers', fn ($monitoringQuery) => $monitoringQuery
                        ->where('user_id', $request->user()->id)
                        ->where('status', 'active')
                        ->whereNull('suspended_at')
                        ->whereNull('revoked_at'));
            })
            ->firstOrFail();

        $accessibleBranches = $business->branches
            ->filter(fn ($candidate): bool => $request->user()->canAccessBranch($business->id, $candidate->id));
        $branch = $request->query('branch_uuid')
            ? $accessibleBranches->firstWhere('uuid', $request->query('branch_uuid'))
            : $accessibleBranches->first();

        abort_if(($request->query('branch_uuid') || $business->branches->isNotEmpty()) && ! $branch, 404);

        return [$business, $branch];
    }
}
