<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Modules\Services\ModuleAccessService;
use App\Domain\Tenancy\Models\Business;
use App\Enums\BusinessModule;
use App\Exceptions\ModuleUnavailableException;
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

    public function services(Request $request, ModuleAccessService $moduleAccess): SuccessResource
    {
        [$business, $branch] = $this->businessAndBranch($request);
        $access = $moduleAccess->resolve($business, BusinessModule::Services, $branch);

        if (! $access->available) {
            throw new ModuleUnavailableException($access);
        }

        return new SuccessResource(['module_access' => $access->toArray(), 'records' => []]);
    }

    public function shareholders(Request $request, ModuleAccessService $moduleAccess): SuccessResource
    {
        [$business, $branch] = $this->businessAndBranch($request);
        $access = $moduleAccess->resolve($business, BusinessModule::Shareholders, $branch);

        if (! $access->available) {
            throw new ModuleUnavailableException($access);
        }

        return new SuccessResource(['module_access' => $access->toArray(), 'records' => []]);
    }

    public function branches(): SuccessResource
    {
        return new SuccessResource(['branches' => []]);
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

        $branch = $request->query('branch_uuid')
            ? $business->branches->firstWhere('uuid', $request->query('branch_uuid'))
            : $business->branches->first();

        abort_if($request->query('branch_uuid') && ! $branch, 404);

        return [$business, $branch];
    }
}