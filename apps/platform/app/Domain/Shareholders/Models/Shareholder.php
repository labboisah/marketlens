<?php

namespace App\Domain\Shareholders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shareholder extends Model
{
    use SoftDeletes;

    protected $table = 'shareholders';
    protected $guarded = [];

    public function shareholdings()
    {
        return $this->hasMany(Shareholding::class);
    }
}
