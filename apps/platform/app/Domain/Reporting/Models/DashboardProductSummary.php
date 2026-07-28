<?php

namespace App\Domain\Reporting\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardProductSummary extends Model
{
    protected $fillable = [
        'business_id',
        'branch_id',
        'summary_date',
        'product_uuid',
        'product_name',
        'sku',
        'quantity_sold',
        'sales_minor',
        'days_without_sale',
        'is_low_stock',
        'stock_quantity',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'quantity_sold' => 'decimal:4',
            'stock_quantity' => 'decimal:4',
            'is_low_stock' => 'boolean',
        ];
    }
}
