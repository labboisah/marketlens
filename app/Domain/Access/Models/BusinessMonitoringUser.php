<?php

namespace App\Domain\Access\Models;

use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BusinessMonitoringUser extends Model
{
    protected $fillable = [
        'business_id',
        'user_id',
        'monitoring_role_id',
        'status',
        'suspended_at',
        'revoked_at',
        'invited_by',
        'module_permissions',
    ];

    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
            'revoked_at' => 'datetime',
            'module_permissions' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(MonitoringRole::class, 'monitoring_role_id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'business_monitoring_user_branch')->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->suspended_at === null && $this->revoked_at === null;
    }
}
