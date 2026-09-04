<?php

namespace App\Domain\Services\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceBranchSetting extends Model
{
    use SoftDeletes;

    protected $table = 'service_branch_settings';

    protected $guarded = [];
}
