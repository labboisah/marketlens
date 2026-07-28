<?php

namespace App\Domain\Tenancy\Models;

use App\Enums\BusinessStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = ['uuid', 'name', 'status'];

    protected function casts(): array
    {
        return ['status' => BusinessStatus::class];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
