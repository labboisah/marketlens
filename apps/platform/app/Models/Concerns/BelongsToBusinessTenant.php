<?php

namespace App\Models\Concerns;

use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Scopes\BusinessTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToBusinessTenant
{
    protected static function bootBelongsToBusinessTenant(): void
    {
        static::addGlobalScope(new BusinessTenantScope);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
