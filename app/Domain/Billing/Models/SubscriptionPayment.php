<?php

namespace App\Domain\Billing\Models;

use App\Casts\MoneyCast;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionPayment extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = ['subscription_id', 'uuid', 'amount_minor', 'currency', 'status', 'paid_at'];

    protected function casts(): array
    {
        return [
            'amount_minor' => MoneyCast::class,
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
