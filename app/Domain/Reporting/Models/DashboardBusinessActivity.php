<?php

namespace App\Domain\Reporting\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardBusinessActivity extends Model
{
    protected $fillable = [
        'business_id',
        'branch_id',
        'type',
        'title',
        'description',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }
}
