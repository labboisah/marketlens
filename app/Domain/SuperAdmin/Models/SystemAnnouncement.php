<?php

namespace App\Domain\SuperAdmin\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemAnnouncement extends Model
{
    protected $fillable = ['created_by', 'title', 'body', 'severity', 'is_published', 'published_at', 'expires_at'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'published_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
