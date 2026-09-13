<?php

declare(strict_types=1);

namespace App\Domain\Production;

use App\Domain\Synchronization\Services\SynchronizedOperationalData;
use App\Domain\Tenancy\Models\Business;
use App\Models\User;

class ProductionMonitoring
{
    public function data(User $user, Business $business, ?int $branchId): array
    {
        abort_unless($user->hasActiveAccount() && $user->hasBusinessPermission($business, 'view-production'), 403);
        $branches = $business->branches()->get()->filter(fn ($branch) => $user->canAccessBranch($business->id, $branch->id));
        if ($branchId !== null) {
            abort_unless($branches->contains('id', $branchId), 403);
            $branches = $branches->where('id', $branchId);
        }
        $finance = $user->hasBusinessPermission($business, 'view-production-finance');
        $records = $branches->flatMap(fn ($branch) => app(SynchronizedOperationalData::class)->records($business->id, ['production_record'], $branch->id));
        $records = $records->filter(fn ($row) => $finance || in_array($row['kind'] ?? '', ['material', 'machine', 'staff', 'recipe', 'batch', 'transfer'], true))
            ->map(function ($row) use ($finance) {
                if (! $finance && ($row['kind'] ?? '') === 'batch') {
                    foreach (['rule', 'materialCostMinor', 'expenseCostMinor', 'totalCostMinor'] as $key) {
                        unset($row['data'][$key]);
                    }
                    $row['data']['materials'] = collect($row['data']['materials'] ?? [])->map(fn ($line) => array_intersect_key($line, array_flip(['productUuid', 'name', 'quantity', 'returned'])))->all();
                }

                return $row;
            })->values();
        $sales = $records->where('kind', 'sale');
        $losses = $records->where('kind', 'loss')->sum('data.amountMinor');
        $allocated = $records->where('kind', 'allocation')->sum(fn ($row) => collect($row['data']['entries'] ?? [])->sum('amountMinor'));
        $paid = $records->where('kind', 'settlement')->sum('data.amountMinor');

        return [
            'records' => $records, 'finance' => $finance,
            'approvedShareMinor' => $finance ? $allocated : null,
            'businessRemainderMinor' => $finance ? $sales->sum('data.profitMinor') - $losses - $allocated : null,
            'outstandingShareMinor' => $finance ? $allocated - $paid : null,
            'revenueMinor' => $finance ? $sales->sum('data.revenueMinor') : null,
            'costMinor' => $finance ? $sales->sum('data.costMinor') + $losses : null,
            'profitMinor' => $finance ? $sales->sum('data.profitMinor') - $losses : null,
            'lastSyncedAt' => $records->max('_occurred_at'),
        ];
    }
}
