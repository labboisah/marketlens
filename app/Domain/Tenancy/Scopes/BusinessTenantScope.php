<?php

namespace App\Domain\Tenancy\Scopes;

use App\Domain\Tenancy\Services\BusinessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BusinessTenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $businessId = app(BusinessContext::class)->id();

        if ($businessId !== null) {
            $builder->where($model->qualifyColumn('business_id'), $businessId);
        }
    }
}
