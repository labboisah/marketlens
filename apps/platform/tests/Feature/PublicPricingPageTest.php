<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPricingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_plans_are_displayed_and_inactive_plans_are_hidden(): void
    {
        $this->seed(PlanSeeder::class);

        Plan::query()->create([
            'name' => 'Hidden',
            'slug' => 'hidden',
            'description' => 'This inactive plan should not be public.',
            'billing_interval' => 'monthly',
            'price_minor' => 1_000,
            'currency' => 'NGN',
            'trial_days' => 14,
            'sort_order' => 99,
            'is_active' => false,
        ]);

        $response = $this->get('/pricing');

        $response
            ->assertOk()
            ->assertSee('Basic')
            ->assertSee('Standard')
            ->assertSee('Professional')
            ->assertDontSee('Hidden');
    }

    public function test_plan_features_are_rendered_from_plan_feature_records(): void
    {
        $this->seed(PlanSeeder::class);

        $response = $this->get('/pricing');

        $response
            ->assertOk()
            ->assertSee('One business')
            ->assertSee('Sales')
            ->assertSee('Purchases and suppliers')
            ->assertSee('Cashier settlement')
            ->assertSee('Multiple branches')
            ->assertSee('Advanced audit reports')
            ->assertSee('aria-label="Sales details"', false);
    }

    public function test_monthly_and_annual_values_are_displayed_from_configured_plan_data(): void
    {
        $this->seed(PlanSeeder::class);

        $response = $this->get('/pricing');

        $response
            ->assertOk()
            ->assertSee('Monthly')
            ->assertSee('Annual')
            ->assertSee('NGN 15,000')
            ->assertSee('NGN 150,000')
            ->assertSee('NGN 35,000')
            ->assertSee('NGN 350,000');
    }

    public function test_pricing_page_has_correct_registration_links(): void
    {
        $this->seed(PlanSeeder::class);

        $response = $this->get('/pricing');

        $response
            ->assertOk()
            ->assertSee('href="/register?plan=basic"', false)
            ->assertSee('href="/register?plan=standard"', false)
            ->assertSee('href="/register?plan=professional"', false);
    }

    public function test_authenticated_users_see_current_plan_status_placeholder(): void
    {
        $this->seed(PlanSeeder::class);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/pricing');

        $response
            ->assertOk()
            ->assertSee('Current plan status');
    }

    public function test_pricing_page_explains_hosting_and_domain_expectations(): void
    {
        $this->seed(PlanSeeder::class);

        $response = $this->get('/pricing');

        $response
            ->assertOk()
            ->assertSee('Hosting and Platform access are included')
            ->assertSee('customers do not need to purchase a domain');
    }
}
