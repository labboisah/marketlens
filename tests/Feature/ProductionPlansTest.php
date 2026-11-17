<?php

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Services\ProductionPlanFeature;
use App\Livewire\SuperAdmin\BillingPlansPage;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('seeds Production for both Professional intervals and preserves module keys', function () {
    $this->seed(PlanSeeder::class);
    $this->seed(PlanSeeder::class);
    foreach (Plan::all() as $plan) {
        expect(ProductionPlanFeature::query($plan)->count())->toBe($plan->slug === 'professional' ? 1 : 0);
        if ($plan->slug === 'professional') {
            expect($plan->features()->pluck('key')->all())->toContain('module:production', 'module:services', 'module:shareholders');
        }
    }
    $this->get('/pricing')->assertOk()->assertSee('Production and Profit Sharing');
});

it('upgrades existing Professional plans idempotently without changing prices or other plans', function () {
    $this->seed(PlanSeeder::class);
    foreach (Plan::all() as $plan) {
        ProductionPlanFeature::query($plan)->delete();
    }
    $before = Plan::all()->mapWithKeys(fn ($plan) => [$plan->id => $plan->getRawOriginal('price_minor')])->all();
    $migration = require database_path('migrations/2026_09_13_000002_add_production_to_professional_plans.php');
    $migration->up();
    $migration->up();
    expect(Plan::all()->mapWithKeys(fn ($plan) => [$plan->id => $plan->getRawOriginal('price_minor')])->all())->toBe($before);
    foreach (Plan::all() as $plan) {
        expect(ProductionPlanFeature::query($plan)->count())->toBe($plan->slug === 'professional' ? 1 : 0);
    }
});

it('allows administrators to enable and remove Production on an existing plan', function () {
    $this->seed(PlanSeeder::class);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $plan = Plan::where('slug', 'standard')->where('billing_interval', 'monthly')->firstOrFail();
    Livewire::actingAs($admin)->test(BillingPlansPage::class)->call('startEditingPlan', $plan->id)
        ->assertSet('productionEnabled', false)->set('productionEnabled', true)->call('saveEditedPlan')->assertHasNoErrors();
    expect(ProductionPlanFeature::query($plan)->count())->toBe(1);
    expect((int) $plan->fresh()->getRawOriginal('price_minor'))->toBe(3500000);
    Livewire::actingAs($admin)->test(BillingPlansPage::class)->call('startEditingPlan', $plan->id)
        ->assertSet('productionEnabled', true)->set('productionEnabled', false)->call('saveEditedPlan')->assertHasNoErrors();
    expect(ProductionPlanFeature::query($plan)->count())->toBe(0);
    expect($plan->features()->where('name', 'Purchases and suppliers')->exists())->toBeTrue();
});

it('saves Production on a new plan with the entitlement key', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    Livewire::actingAs($admin)->test(BillingPlansPage::class)
        ->set('name', 'Manufacturing')->set('slug', 'manufacturing')->set('priceMinor', 12345)->set('productionEnabled', true)
        ->call('createPlan')->assertHasNoErrors();
    expect(ProductionPlanFeature::query(Plan::firstOrFail())->value('key'))->toBe('module:production');
    expect((int) Plan::firstOrFail()->getRawOriginal('price_minor'))->toBe(12345);
});

it('does not allow a non-administrator to change plan production entitlement', function () {
    $user = User::factory()->create(['is_super_admin' => false]);
    Livewire::actingAs($user)->test(BillingPlansPage::class)->assertForbidden();
});

it('shows Production inclusion and capabilities directly on plan cards and the billing list', function () {
    $this->seed(PlanSeeder::class);
    $professional = Plan::where('slug', 'professional')->where('billing_interval', 'monthly')->firstOrFail();
    $basic = Plan::where('slug', 'basic')->where('billing_interval', 'monthly')->firstOrFail();
    $this->get('/pricing')->assertOk()
        ->assertSee('data-production-plan="'.$professional->id.'" data-production-included="true"', false)
        ->assertSee('data-production-plan="'.$basic->id.'" data-production-included="false"', false)
        ->assertSee('Production module: Included')
        ->assertSee('Production module: Not included')
        ->assertSee('machines and staff');
    $admin = User::factory()->create(['is_super_admin' => true]);
    Livewire::actingAs($admin)->test(BillingPlansPage::class)
        ->assertSeeHtml('data-production-plan="'.$professional->id.'" data-production-included="true"')
        ->assertSeeHtml('data-production-plan="'.$basic->id.'" data-production-included="false"')
        ->assertSee('owner-configured machine and staff profit shares');
});
