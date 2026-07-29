<?php

namespace App\Domain\Customers\Models;

use App\Models\Concerns\BelongsToBranchTenant;
use App\Models\Concerns\BelongsToBusinessTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplicatedCustomerDebtPayment extends Model
{
    use BelongsToBranchTenant;
    use BelongsToBusinessTenant;

    protected $fillable = [
        'business_id',
        'branch_id',
        'customer_debt_summary_id',
        'uuid',
        'customer_uuid',
        'customer_name',
        'amount_minor',
        'payment_method',
        'reference',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }

    public function summary(): BelongsTo
    {
        return $this->belongsTo(ReplicatedCustomerDebtSummary::class, 'customer_debt_summary_id');
    }
}
