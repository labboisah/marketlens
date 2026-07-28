<?php

namespace App\Domain\Owners\Models;

use App\Domain\Tenancy\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Pivot;

class BusinessOwner extends Pivot
{
    protected $table = 'business_owners';

    protected $fillable = ['business_id', 'user_id', 'role'];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
