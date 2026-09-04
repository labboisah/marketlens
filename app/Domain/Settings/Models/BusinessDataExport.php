<?php

namespace App\Domain\Settings\Models;

use App\Domain\Tenancy\Models\Business;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessDataExport extends Model
{
    use HasUuid;

    protected $fillable = [
        'business_id',
        'requested_by',
        'uuid',
        'status',
        'format',
        'filters',
        'payload',
        'requested_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'requested_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
