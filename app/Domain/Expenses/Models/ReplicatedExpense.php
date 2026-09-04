<?php

namespace App\Domain\Expenses\Models;

use App\Domain\Devices\Models\Device;
use App\Domain\Synchronization\Models\OperationalUserProfile;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Models\Concerns\BelongsToBranchTenant;
use App\Models\Concerns\BelongsToBusinessTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplicatedExpense extends Model
{
    use BelongsToBranchTenant;
    use BelongsToBusinessTenant;

    protected $fillable = [
        'business_id',
        'branch_id',
        'device_id',
        'sync_batch_id',
        'uuid',
        'expense_number',
        'category_uuid',
        'category_name',
        'description',
        'amount_minor',
        'payment_method',
        'creator_uuid',
        'creator_name',
        'approver_uuid',
        'approver_name',
        'status',
        'expense_date',
        'receipt_attachment_path',
        'is_recurring',
        'recurring_period',
        'source_version',
        'source_idempotency_key',
        'source_occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'datetime',
            'is_recurring' => 'boolean',
            'source_occurred_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function syncBatch(): BelongsTo
    {
        return $this->belongsTo(SyncBatch::class);
    }

    public function creatorProfile(): BelongsTo
    {
        return $this->belongsTo(OperationalUserProfile::class, 'creator_uuid', 'uuid');
    }

    public function approverProfile(): BelongsTo
    {
        return $this->belongsTo(OperationalUserProfile::class, 'approver_uuid', 'uuid');
    }
}
