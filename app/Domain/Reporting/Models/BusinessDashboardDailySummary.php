<?php

namespace App\Domain\Reporting\Models;

use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessDashboardDailySummary extends Model
{
    protected $fillable = [
        'business_id',
        'branch_id',
        'summary_date',
        'gross_sales_minor',
        'net_sales_minor',
        'completed_sales_count',
        'cash_payments_minor',
        'bank_transfer_payments_minor',
        'pos_terminal_payments_minor',
        'credit_sales_minor',
        'expenses_minor',
        'gross_profit_minor',
        'estimated_net_profit_minor',
        'customer_debt_minor',
        'supplier_balance_minor',
        'current_stock_value_minor',
        'low_stock_products_count',
        'cashier_variance_minor',
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

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
