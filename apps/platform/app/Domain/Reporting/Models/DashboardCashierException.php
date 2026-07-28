<?php

namespace App\Domain\Reporting\Models;

use Illuminate\Database\Eloquent\Model;

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
}
