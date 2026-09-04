<?php

namespace App\Domain\Billing\Models;

use App\Domain\Tenancy\Models\Business;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionChangeRequest extends Model
{
    use HasUuid;

    protected $fillable = [
        'business_id',
        'subscription_id',
        'requested_plan_id',
        'requested_by',
        'uuid',
        'type',
        'status',
        'warnings',
        'usage_snapshot',
        'gateway_payload',
        'reason',
        'requested_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'warnings' => 'array',
            'usage_snapshot' => 'array',
            'gateway_payload' => 'array',
            'requested_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function requestedPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'requested_plan_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
