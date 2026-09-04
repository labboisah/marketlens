<?php

namespace App\Models\Concerns;

use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToBranchTenant
{
    protected static function bootBelongsToBranchTenant(): void
    {
        static::addGlobalScope(new BranchScope);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
