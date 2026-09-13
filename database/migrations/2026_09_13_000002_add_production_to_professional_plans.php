<?php

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Services\ProductionPlanFeature;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Plan::query()->where('slug', 'professional')->whereIn('billing_interval', ['monthly', 'annual'])
            ->each(fn (Plan $plan) => app(ProductionPlanFeature::class)->sync($plan, true));
    }

    public function down(): void
    {
        // Preserve granted subscription entitlements on code rollback.
        // Administrators can explicitly remove Production through the plan editor.
    }
};
