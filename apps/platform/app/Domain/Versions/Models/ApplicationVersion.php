<?php

namespace App\Domain\Versions\Models;

use App\Enums\ApplicationVersionStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApplicationVersion extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = ['uuid', 'application', 'version', 'status', 'release_notes', 'released_at'];

    protected function casts(): array
    {
        return [
            'status' => ApplicationVersionStatus::class,
            'released_at' => 'datetime',
        ];
    }
}
