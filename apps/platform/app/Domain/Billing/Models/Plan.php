<?php

namespace App\Domain\Billing\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = ['uuid', 'name', 'billing_interval', 'price_minor', 'currency', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price_minor' => MoneyCast::class,
        ];
    }
}
