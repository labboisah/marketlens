<?php

namespace App\Domain\Tenancy\Scopes;

use App\Domain\Tenancy\Services\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $branchId = app(BranchContext::class)->id();

        if ($branchId !== null) {
            $builder->where($model->qualifyColumn('branch_id'), $branchId);
        }
    }
}
