<?php

namespace App\Domain\Onboarding\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerOnboardingDraft extends Model
{
    protected $fillable = ['user_id', 'current_step', 'data', 'completed_at'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
