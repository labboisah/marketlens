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
