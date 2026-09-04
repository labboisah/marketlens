<?php

namespace App\Domain\SuperAdmin\Models;

use App\Domain\Tenancy\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportAccessRequest extends Model
{
    protected $fillable = ['business_id', 'requested_by', 'approved_by', 'reason', 'status', 'approved_at', 'expires_at'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
