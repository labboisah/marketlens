<?php

namespace App\Domain\Shareholders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shareholding extends Model
{
    use SoftDeletes;

    protected $table = 'shareholdings';
    protected $guarded = [];
}
