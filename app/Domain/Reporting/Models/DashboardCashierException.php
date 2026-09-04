<?php

namespace App\Domain\Reporting\Models;

use App\Domain\Synchronization\Models\OperationalUserProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardCashierException extends Model
{
    protected $fillable = [
        'business_id',
        'branch_id',
        'summary_date',
        'cashier_uuid',
        'cashier_name',
        'variance_minor',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
        ];
    }

    public function operationalUserProfile(): BelongsTo
    {
        return $this->belongsTo(OperationalUserProfile::class, 'cashier_uuid', 'uuid');
    }
}
