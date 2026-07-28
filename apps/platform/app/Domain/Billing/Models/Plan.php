<?php

namespace App\Domain\Billing\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'billing_interval',
        'price_minor',
        'currency',
        'trial_days',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price_minor' => MoneyCast::class,
        ];
    }

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class)->orderBy('sort_order')->orderBy('name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getDisplayPriceAttribute(): string
    {
        return $this->currency.' '.number_format(((int) $this->getRawOriginal('price_minor')) / 100);
    }

    public function getBillingLabelAttribute(): string
    {
        return match ($this->billing_interval) {
            'annual' => 'Annual',
            'monthly' => 'Monthly',
            default => ucfirst((string) $this->billing_interval),
        };
    }
}
