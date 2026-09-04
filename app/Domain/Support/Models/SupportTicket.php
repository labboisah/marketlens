<?php

namespace App\Domain\Support\Models;

use App\Domain\Devices\Models\Device;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\SupportTicketStatus;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicket extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'support_ticket_category_id',
        'created_by',
        'assigned_to',
        'branch_id',
        'device_id',
        'sync_batch_id',
        'uuid',
        'ticket_number',
        'subject',
        'status',
        'priority',
        'description',
        'last_message_at',
        'closed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupportTicketStatus::class,
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SupportTicketCategory::class, 'support_ticket_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function syncBatch(): BelongsTo
    {
        return $this->belongsTo(SyncBatch::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'support_ticket_id')->oldest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class, 'support_ticket_id')->latest();
    }
}
