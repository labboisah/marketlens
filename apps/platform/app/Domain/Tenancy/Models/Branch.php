<?php

namespace App\Domain\Tenancy\Models;

use App\Domain\Devices\Models\Device;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Enums\BranchStatus;
use App\Models\Concerns\BelongsToBusinessTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use BelongsToBusinessTenant;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'uuid',
        'name',
        'code',
        'phone',
        'email',
        'address',
        'state',
        'local_government_area',
        'is_head_office',
        'module_settings',
        'last_synchronized_at',
        'archived_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => BranchStatus::class,
            'is_head_office' => 'boolean',
            'module_settings' => 'array',
            'last_synchronized_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function syncBatches(): HasMany
    {
        return $this->hasMany(SyncBatch::class);
    }

    public function canActivateShopDevices(): bool
    {
        return $this->status === BranchStatus::Active;
    }
}
