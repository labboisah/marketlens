<?php

namespace App\Domain\Services\Http\Controllers\Platform;

use App\Domain\Modules\Services\ModuleAccessService;
use App\Domain\Synchronization\Services\SynchronizedOperationalData;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\BusinessModule;
use App\Enums\BranchOperatingMode;
use App\Exceptions\ModuleUnavailableException;
use App\Exceptions\OperatingModeUnavailableException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceMonitoringController extends Controller
{
    public function dashboard(Request $request, ModuleAccessService $moduleAccess, SynchronizedOperationalData $synchronizedData): JsonResponse
    {
        [$business, $branch] = $this->businessAndBranch($request);
        $this->ensureAvailable($business, $branch, $moduleAccess);
        $data = $synchronizedData->services($business->id, $branch?->id);
        $summary = $data['summary'];

        return response()->json([
            'status' => 'ok',
            'data' => [
                'services_rendered' => $summary['orders_count'],
                'services_completed' => $summary['completed_count'],
                'in_progress' => $summary['in_progress_count'],
                'service_revenue' => $summary['payments_total'],
                'direct_expenses' => 0,
                'distributable_amount' => $summary['business_share'] + $summary['staff_share'] + $summary['shareholder_share'],
                'staff_share' => $summary['staff_share'],
                'business_share' => $summary['business_share'],
                'shareholder_share' => $summary['shareholder_share'],
                'expense_reserve' => 0,
                'outstanding_payments' => max(0, $data['orders']->sum('total_amount') - $summary['payments_total']),
                'pending_staff_earnings' => $data['staff_earnings']->sum(fn (array $earning): float => (float) ($earning['amount'] ?? 0)),
                'settled_staff_earnings' => 0,
                'average_service_value' => $summary['orders_count'] > 0 ? $summary['payments_total'] / $summary['orders_count'] : 0,
                'last_sync' => $summary['last_sync'],
            ],
        ]);
    }

    public function orders(Request $request, ModuleAccessService $moduleAccess, SynchronizedOperationalData $synchronizedData): JsonResponse
    {
        [$business, $branch] = $this->businessAndBranch($request);
        $this->ensureAvailable($business, $branch, $moduleAccess);
        $perPage = max(1, min(100, (int) $request->integer('per_page', 25)));
        $orders = $synchronizedData->services($business->id, $branch?->id)['orders'];

        return response()->json([
            'data' => $orders->take($perPage)->values(),
            'meta' => ['per_page' => $perPage, 'total' => $orders->count()],
        ]);
    }

    public function showOrder(Request $request, string $uuid, ModuleAccessService $moduleAccess, SynchronizedOperationalData $synchronizedData): JsonResponse
    {
        [$business, $branch] = $this->businessAndBranch($request);
        $this->ensureAvailable($business, $branch, $moduleAccess);
        $order = $synchronizedData->services($business->id, $branch?->id)['orders']
            ->first(fn (array $record): bool => ($record['uuid'] ?? $record['_record_uuid']) === $uuid);

        abort_unless($order, 404);

        return response()->json(['data' => $order]);
    }

    public function payments(Request $request, ModuleAccessService $moduleAccess, SynchronizedOperationalData $synchronizedData): JsonResponse
    {
        return response()->json(['data' => $this->serviceData($request, $moduleAccess, $synchronizedData)['payments']]);
    }

    public function expenses(Request $request, ModuleAccessService $moduleAccess): JsonResponse
    {
        [$business, $branch] = $this->businessAndBranch($request);
        $this->ensureAvailable($business, $branch, $moduleAccess);

        return response()->json(['data' => []]);
    }

    public function allocations(Request $request, ModuleAccessService $moduleAccess, SynchronizedOperationalData $synchronizedData): JsonResponse
    {
        return response()->json(['data' => $this->serviceData($request, $moduleAccess, $synchronizedData)['allocations']]);
    }

    public function settlements(Request $request, ModuleAccessService $moduleAccess, SynchronizedOperationalData $synchronizedData): JsonResponse
    {
        return response()->json(['data' => $this->serviceData($request, $moduleAccess, $synchronizedData)['staff_earnings']]);
    }

    /** @return array<string, mixed> */
    private function serviceData(Request $request, ModuleAccessService $moduleAccess, SynchronizedOperationalData $synchronizedData): array
    {
        [$business, $branch] = $this->businessAndBranch($request);
        $this->ensureAvailable($business, $branch, $moduleAccess);

        return $synchronizedData->services($business->id, $branch?->id);
    }

    private function ensureAvailable(Business $business, ?Branch $branch, ModuleAccessService $moduleAccess): void
    {
        $access = $moduleAccess->resolve($business, BusinessModule::Services, $branch);

        if (! $access->available) {
            throw new ModuleUnavailableException($access);
        }

        if ($branch && ! in_array(BranchOperatingMode::Services->value, $branch->operating_modes ?? [], true)) {
            throw new OperatingModeUnavailableException(BranchOperatingMode::Services);
        }
    }

    /** @return array{0: Business, 1: Branch|null} */
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
            ->filter(fn (Branch $candidate): bool => $request->user()->canAccessBranch($business->id, $candidate->id));
        $branch = $request->query('branch_uuid')
            ? $accessibleBranches->firstWhere('uuid', $request->query('branch_uuid'))
            : $accessibleBranches->first();

        abort_if(($request->query('branch_uuid') || $business->branches->isNotEmpty()) && ! $branch, 404);

        return [$business, $branch];
    }
}
