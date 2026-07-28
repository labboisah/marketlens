<?php

namespace App\Domain\Tenancy\Models;

use App\Enums\BranchStatus;
use App\Models\Concerns\BelongsToBusinessTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use BelongsToBusinessTenant;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'uuid',
        'name',
        'code',
        'phone',
        'email',
        'address',
        'state',
        'local_government_area',
        'is_head_office',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => BranchStatus::class,
            'is_head_office' => 'boolean',
        ];
    }
}
