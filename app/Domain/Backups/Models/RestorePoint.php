<?php

namespace App\Domain\Backups\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestorePoint extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['captured_at' => 'datetime', 'schema_version' => 'integer', 'size' => 'integer'];
    }
}
