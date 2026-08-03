<?php

namespace App\Domain\Services\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceRevenueShareRule extends Model
{
    use SoftDeletes;

    protected $table = 'service_revenue_share_rules';

    protected $guarded = [];

    public function components()
    {
        return $this->hasMany(ServiceRevenueShareRuleComponent::class, 'rule_id');
    }
}
