<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformNotification extends Model
{
    use HasUuid;

    protected $fillable = [
        'business_id',
        'branch_id',
        'uuid',
        'type',
        'severity',
        'category',
        'status',
        'title',
        'body',
        'related_record_type',
        'related_record_uuid',
        'dedupe_key',
        'channels',
        'delivered_channels',
        'read_at',
        'resolved_at',
        'resolution_note',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'delivered_channels' => 'array',
            'read_at' => 'datetime',
            'resolved_at' => 'datetime',
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

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
