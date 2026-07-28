<?php

namespace App\Domain\Devices\Models;

use App\Enums\DeviceStatus;
use App\Models\Concerns\BelongsToBranchTenant;
use App\Models\Concerns\BelongsToBusinessTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use BelongsToBranchTenant;
    use BelongsToBusinessTenant;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = ['business_id', 'branch_id', 'uuid', 'name', 'type', 'status', 'last_seen_at'];

    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'last_seen_at' => 'datetime',
        ];
    }
}
