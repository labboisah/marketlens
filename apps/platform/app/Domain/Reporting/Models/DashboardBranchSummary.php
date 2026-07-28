<?php

namespace App\Domain\Reporting\Models;

use App\Domain\Tenancy\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardBranchSummary extends Model
{
    protected $fillable = [
        'business_id',
        'branch_id',
        'summary_date',
        'gross_sales_minor',
        'net_sales_minor',
        'completed_sales_count',
        'cashier_variance_minor',
        'last_successful_sync_at',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'last_successful_sync_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
