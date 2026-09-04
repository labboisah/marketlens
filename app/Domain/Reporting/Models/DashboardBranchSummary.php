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
        'gross_profit_minor',
        'expenses_minor',
        'estimated_net_profit_minor',
        'stock_value_minor',
        'customer_debt_minor',
        'supplier_balance_minor',
        'cashier_variance_minor',
        'low_stock_count',
        'active_client_apps_count',
        'last_successful_sync_at',
        'summarized_at',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'last_successful_sync_at' => 'datetime',
            'summarized_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
