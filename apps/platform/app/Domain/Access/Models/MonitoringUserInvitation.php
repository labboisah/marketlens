<?php

namespace App\Domain\Access\Models;

use App\Domain\Tenancy\Models\Business;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringUserInvitation extends Model
{
    use HasUuid;

    protected $fillable = [
        'business_id',
        'monitoring_role_id',
        'invited_by',
        'uuid',
        'email',
        'token_hash',
        'branch_ids',
        'module_permissions',
        'status',
        'expires_at',
        'accepted_at',
        'cancelled_at',
        'last_sent_at',
        'send_count',
    ];

    protected function casts(): array
    {
        return [
            'branch_ids' => 'array',
            'module_permissions' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(MonitoringRole::class, 'monitoring_role_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && $this->cancelled_at === null && $this->accepted_at === null && $this->expires_at->isFuture();
    }
}
