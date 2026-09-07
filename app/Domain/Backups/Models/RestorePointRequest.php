<?php

namespace App\Domain\Backups\Models;

use Illuminate\Database\Eloquent\Model;

class RestorePointRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}
