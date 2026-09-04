<?php

namespace App\Domain\SuperAdmin\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuperAdminActionConfirmation extends Model
{
    protected $fillable = ['user_id', 'action', 'confirmation_phrase', 'confirmed_at', 'ip_address', 'user_agent'];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
