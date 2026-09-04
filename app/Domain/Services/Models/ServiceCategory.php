<?php

namespace App\Domain\Services\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceCategory extends Model
{
    use SoftDeletes;

    protected $table = 'service_categories';

    protected $guarded = [];
}
