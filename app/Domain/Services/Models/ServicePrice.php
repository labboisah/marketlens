<?php

namespace App\Domain\Services\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServicePrice extends Model
{
    use SoftDeletes;

    protected $table = 'service_prices';

    protected $guarded = [];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
