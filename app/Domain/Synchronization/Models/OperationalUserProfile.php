<?php

namespace App\Domain\Synchronization\Models;

use App\Domain\Reporting\Models\DashboardCashierException;
use App\Domain\Reporting\Models\StaffActivitySummary;
use App\Models\Concerns\BelongsToBranchTenant;
use App\Models\Concerns\BelongsToBusinessTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationalUserProfile extends Model
{
    use BelongsToBranchTenant;
    use BelongsToBusinessTenant;
    use HasUuid;

    protected $fillable = [
        'business_id',
        'branch_id',
        'uuid',
        'employee_number',
        'display_name',
        'username',
        'staff_code',
        'role_name',
        'role_code',
        'status',
        'is_active',
        'replicated_created_at',
        'replicated_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'replicated_created_at' => 'datetime',
            'replicated_updated_at' => 'datetime',
        ];
    }

    public function activitySummaries(): HasMany
    {
        return $this->hasMany(StaffActivitySummary::class, 'operational_user_uuid', 'uuid');
    }

    public function cashierExceptions(): HasMany
    {
        return $this->hasMany(DashboardCashierException::class, 'cashier_uuid', 'uuid');
    }

    public function statusLabel(): string
    {
        return str($this->status)->replace('_', ' ')->title()->toString();
    }
}
