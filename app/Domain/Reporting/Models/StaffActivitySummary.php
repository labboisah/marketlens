<?php

namespace App\Domain\Reporting\Models;

use App\Domain\Synchronization\Models\OperationalUserProfile;
use App\Models\Concerns\BelongsToBranchTenant;
use App\Models\Concerns\BelongsToBusinessTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffActivitySummary extends Model
{
    use BelongsToBranchTenant;
    use BelongsToBusinessTenant;

    protected $fillable = [
        'business_id',
        'branch_id',
        'operational_user_uuid',
        'summary_date',
        'display_name',
        'role_name',
        'sales_count',
        'gross_sales_minor',
        'voided_sales_count',
        'returns_count',
        'cashier_variance_minor',
        'purchases_recorded_count',
        'stock_counts_count',
        'stock_adjustments_count',
        'damaged_stock_records_count',
        'transfers_processed_count',
        'discounts_approved_count',
        'expenses_approved_count',
        'returns_approved_count',
        'stock_adjustments_approved_count',
        'cashier_settlements_reviewed_count',
        'outside_normal_hours_count',
        'excessive_discount_count',
        'high_cancellation_count',
        'high_return_count',
        'repeated_stock_adjustment_count',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'last_activity_at' => 'datetime',
        ];
    }

    public function operationalUserProfile(): BelongsTo
    {
        return $this->belongsTo(OperationalUserProfile::class, 'operational_user_uuid', 'uuid');
    }
}
