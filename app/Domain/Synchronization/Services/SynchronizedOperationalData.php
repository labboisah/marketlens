<?php

namespace App\Domain\Synchronization\Services;

use App\Domain\Synchronization\Models\SyncRecord;
use Illuminate\Support\Collection;

class SynchronizedOperationalData
{
    /**
     * Return the current accepted version of each synchronized record.
     *
     * @param  list<string>  $entities
     * @return Collection<int, array<string, mixed>>
     */
    public function records(int $businessId, array $entities, ?int $branchId = null): Collection
    {
        return SyncRecord::query()
            ->withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereIn('entity', $entities)
            ->where('status', 'accepted')
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (SyncRecord $record): string => $record->entity.'|'.$record->record_uuid)
            ->reject(fn (SyncRecord $record): bool => $record->operation === 'delete')
            ->map(function (SyncRecord $record): array {
                return array_merge($record->payload ?? [], [
                    '_entity' => $record->entity,
                    '_record_uuid' => $record->record_uuid,
                    '_version' => $record->version,
                    '_occurred_at' => $record->occurred_at,
                ]);
            })
            ->values();
    }

    /** @return array<string, mixed> */
    public function services(int $businessId, ?int $branchId = null): array
    {
        $records = $this->records($businessId, [
            'service',
            'service_price',
            'service_order_item',
            'service_order',
            'service_payment',
            'service_revenue_allocation',
            'service_revenue_share_rule',
            'service_staff_assignment',
            'staff_earning_transaction',
        ], $branchId);

        $orders = $records->where('_entity', 'service_order')->sortByDesc('_occurred_at')->values();
        $payments = $records->where('_entity', 'service_payment')->sortByDesc('_occurred_at')->values();
        $allocations = $records->where('_entity', 'service_revenue_allocation')->sortByDesc('_occurred_at')->values();
        $items = $records->where('_entity', 'service_order_item')->values();
        $prices = $records->where('_entity', 'service_price')
            ->where('active', '1')
            ->sortByDesc('_occurred_at')
            ->unique('service_uuid')
            ->keyBy('service_uuid');
        $services = $records->where('_entity', 'service')
            ->sortBy('name')
            ->map(function (array $service) use ($prices): array {
                $price = $prices[$service['uuid'] ?? $service['_record_uuid']] ?? null;
                $service['current_price'] = (float) ($price['amount'] ?? 0);

                return $service;
            })
            ->values();

        $paidByOrder = $payments->groupBy('order_uuid')->map->sum(fn (array $payment): float => (float) ($payment['amount'] ?? 0));
        $totalByOrder = $items->groupBy('order_uuid')->map->sum(fn (array $item): float => (float) ($item['line_total'] ?? 0));
        $orders = $orders->map(function (array $order) use ($paidByOrder, $totalByOrder): array {
            $uuid = $order['uuid'] ?? $order['_record_uuid'];
            $order['total_amount'] = (float) ($totalByOrder[$uuid] ?? 0) - (float) ($order['discount_amount'] ?? 0);
            $order['paid_amount'] = (float) ($paidByOrder[$uuid] ?? 0);

            return $order;
        });

        $allocationTotals = $allocations->groupBy('component_type')
            ->map->sum(fn (array $allocation): float => (float) ($allocation['amount'] ?? 0));

        return [
            'services' => $services,
            'orders' => $orders,
            'payments' => $payments,
            'allocations' => $allocations,
            'staff_earnings' => $records->where('_entity', 'staff_earning_transaction')->sortByDesc('_occurred_at')->values(),
            'summary' => [
                'services_count' => $services->count(),
                'orders_count' => $orders->count(),
                'completed_count' => $orders->where('status', 'completed')->count(),
                'in_progress_count' => $orders->whereNotIn('status', ['completed', 'cancelled', 'refunded'])->count(),
                'payments_total' => $payments->sum(fn (array $payment): float => (float) ($payment['amount'] ?? 0)),
                'business_share' => (float) ($allocationTotals['business'] ?? 0),
                'staff_share' => (float) ($allocationTotals['staff'] ?? 0),
                'shareholder_share' => (float) ($allocationTotals['shareholders'] ?? 0),
                'last_sync' => $records->max('_occurred_at'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function shareholders(int $businessId, ?int $branchId = null): array
    {
        $records = $this->records($businessId, ['shareholder', 'share_class', 'share_ownership_transaction'], $branchId);
        $shareholders = $records->where('_entity', 'shareholder')->sortBy('name')->values();
        $classes = $records->where('_entity', 'share_class')->sortBy('name')->values();
        $transactions = $records->where('_entity', 'share_ownership_transaction')->sortByDesc('_occurred_at')->values();
        $holdings = [];

        foreach ($transactions->sortBy('_occurred_at') as $transaction) {
            $units = (float) ($transaction['units'] ?? 0);
            $class = (string) ($transaction['share_class_uuid'] ?? 'unclassified');
            $from = $transaction['from_shareholder_uuid'] ?? null;
            $to = $transaction['to_shareholder_uuid'] ?? null;

            if ($from) {
                $holdings[$from][$class] = ($holdings[$from][$class] ?? 0) - $units;
            }
            if ($to) {
                $holdings[$to][$class] = ($holdings[$to][$class] ?? 0) + $units;
            }
        }

        $shareholders = $shareholders->map(function (array $shareholder) use ($holdings): array {
            $uuid = $shareholder['uuid'] ?? $shareholder['_record_uuid'];
            $shareholder['holdings'] = $holdings[$uuid] ?? [];
            $shareholder['total_units'] = array_sum($shareholder['holdings']);

            return $shareholder;
        });

        return [
            'shareholders' => $shareholders,
            'share_classes' => $classes,
            'transactions' => $transactions,
            'summary' => [
                'shareholders_count' => $shareholders->count(),
                'active_count' => $shareholders->where('status', 'active')->count(),
                'share_classes_count' => $classes->count(),
                'units_held' => $shareholders->sum('total_units'),
                'last_sync' => $records->max('_occurred_at'),
            ],
        ];
    }
}
