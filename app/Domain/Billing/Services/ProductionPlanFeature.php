<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Plan;
use Illuminate\Database\Eloquent\Builder;

class ProductionPlanFeature
{
    public const KEY = 'module:production';

    public const NAME = 'Production and Profit Sharing';

    public const TOOLTIP = 'Production materials, recipes, machines, staff, batches, finished stock, sales and expenses, with owner-configured staff and machine profit shares.';

    public static function query(Plan $plan): Builder
    {
        return $plan->features()->getQuery()->where(fn ($q) => $q->where('key', self::KEY)->orWhere('name', self::NAME));
    }

    public function sync(Plan $plan, bool $enabled): void
    {
        if (! $enabled) {
            self::query($plan)->delete();

            return;
        }
        $feature = self::query($plan)->first();
        if ($feature) {
            $feature->update(['key' => self::KEY]);

            return;
        }
        $plan->features()->create([
            'key' => self::KEY, 'name' => self::NAME, 'tooltip' => self::TOOLTIP,
            'is_highlighted' => true, 'sort_order' => ($plan->features()->max('sort_order') ?? 0) + 1,
        ]);
    }
}
