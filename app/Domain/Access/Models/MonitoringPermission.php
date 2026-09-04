<?php

namespace App\Domain\Access\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MonitoringPermission extends Model
{
    protected $fillable = ['name', 'slug'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(MonitoringRole::class, 'monitoring_permission_role')->withTimestamps();
    }
}
