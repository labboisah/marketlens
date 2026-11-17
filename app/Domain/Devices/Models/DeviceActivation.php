<?php

namespace App\Domain\Devices\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeviceActivation extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'device_id',
        'application_version_id',
        'uuid',
        'activation_code_hash',
        'response_encrypted',
        'activated_at',
        'used_at',
        'revoked_at',
        'failed_attempts',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
