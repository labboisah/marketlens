<?php

namespace App\Domain\Services\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use SoftDeletes;

    protected $table = 'services';

    protected $guarded = [];

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function prices()
    {
        return $this->hasMany(ServicePrice::class, 'service_id');
    }
}
