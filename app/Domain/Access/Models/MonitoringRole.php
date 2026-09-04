<?php

namespace App\Domain\Access\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoringRole extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_owner_role'];

    protected function casts(): array
    {
        return ['is_owner_role' => 'boolean'];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(MonitoringPermission::class, 'monitoring_permission_role')->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BusinessMonitoringUser::class);
    }
}
