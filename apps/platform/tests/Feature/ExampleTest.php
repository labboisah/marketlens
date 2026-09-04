<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_welcome_route_loads(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('MarketLens')
            ->assertSee('See Every Sale. Control Every Stock.')
            ->assertSee('offline-first sales, inventory, debt, expense and business-monitoring platform');
    }

    public function test_public_navigation_links_exist(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('/product"', false)
            ->assertSee('Product')
            ->assertSee('/features"', false)
            ->assertSee('Features')
            ->assertSee('/business-types"', false)
            ->assertSee('Business types')
            ->assertSee('/pricing"', false)
            ->assertSee('Pricing')
            ->assertSee('/how-it-works"', false)
            ->assertSee('How it works')
            ->assertSee('FAQ')
            ->assertSee('Contact');
    }

    public function test_welcome_page_main_sections_exist(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Business problems')
            ->assertSee('Product solution')
            ->assertSee('How MarketLens works')
            ->assertSee('Core features')
            ->assertSee('Business types')
            ->assertSee('Offline-first')
            ->assertSee('Remote monitoring')
            ->assertSee('Pricing preview')
            ->assertSee('FAQ')
            ->assertSee('Contact');
    }

    public function test_welcome_page_problem_and_solution_content_exists(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Incomplete sales records')
            ->assertSee('Incorrect stock balances')
            ->assertSee('Cashier shortages')
            ->assertSee('Weak debt management')
            ->assertSee('Unreliable internet')
            ->assertSee('Offline business operation')
            ->assertSee('Local Business Server')
            ->assertSee('Cloud synchronization')
            ->assertSee('Debt ledger')
            ->assertSee('Owner dashboards');
    }

    public function test_how_marketlens_works_steps_exist(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Client Apps process business operations.')
            ->assertSee('Business Server validates and stores records locally.')
            ->assertSee('Business Server synchronizes records to the Platform.')
            ->assertSee('Monitoring App displays synchronized business records.');
    }

    public function test_core_features_and_business_types_exist(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Sales monitoring')
            ->assertSee('Inventory control')
            ->assertSee('Customer debts')
            ->assertSee('Supplier accounts')
            ->assertSee('Synchronization status')
            ->assertSee('Security alerts')
            ->assertSee('Supermarkets')
            ->assertSee('Provision businesses')
            ->assertSee('Wholesalers')
            ->assertSee('Pharmacies')
            ->assertSee('Spare-parts shops');
    }

    public function test_remote_monitoring_demonstration_metrics_exist(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Demonstration data')
            ->assertSee('Today&#039;s sales', false)
            ->assertSee('Cash received')
            ->assertSee('Transfer payments')
            ->assertSee('Expenses')
            ->assertSee('Gross profit')
            ->assertSee('Low-stock products')
            ->assertSee('Customer debt')
            ->assertSee('Cashier shortage')
            ->assertSee('Last synchronization');
    }

    public function test_faq_questions_exist(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Does each business need a domain?')
            ->assertSee('Does each business need a cloud server?')
            ->assertSee('Can the business operate without internet?')
            ->assertSee('Can multiple computers connect?')
            ->assertSee('Where are staff users created?')
            ->assertSee('Can the owner monitor remotely?')
            ->assertSee('What happens when synchronization is delayed?')
            ->assertSee('Is business data separated securely?')
            ->assertSee('Can a business manage multiple branches?');
    }

    public function test_login_and_registration_actions_exist(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('href="/login"', false)
            ->assertSee('Login')
            ->assertSee('href="/register"', false)
            ->assertSee('Get Started')
            ->assertSee('Start Free Trial')
            ->assertSee('Request Demonstration')
            ->assertSee('View Features');
    }

    public function test_welcome_page_seo_metadata_exists(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('<meta name="description"', false)
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('<meta property="og:title"', false)
            ->assertSee('<meta property="og:description"', false)
            ->assertSee('SoftwareApplication');
    }

    public function test_mobile_menu_is_accessible(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('aria-controls="public-mobile-menu"', false)
            ->assertSee('aria-label="Toggle main navigation"', false)
            ->assertSee('aria-label="Mobile navigation"', false)
            ->assertSee('id="public-mobile-menu"', false);
    }

    public function test_public_home_does_not_expose_authenticated_data(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertDontSee('Platform administration')
            ->assertDontSee('Tenant Context')
            ->assertDontSee('Full sales, stock, purchase, and ledger synchronization logic has not been implemented')
            ->assertDontSee('admin/dashboard');
    }

    public function test_product_page_loads_with_product_architecture_content(): void
    {
        $response = $this->get('/product');

        $response
            ->assertOk()
            ->assertSee('Home')
            ->assertSee('Product')
            ->assertSee('Platform')
            ->assertSee('Business Server')
            ->assertSee('Client App')
            ->assertSee('Monitoring App')
            ->assertSee('Data flow')
            ->assertSee('Offline-first approach')
            ->assertSee('Remote monitoring')
            ->assertSee('Security')
            ->assertSee('Multi-branch operation');
    }

    public function test_features_page_groups_features_with_problem_solution_and_benefit(): void
    {
        $response = $this->get('/features');

        $response
            ->assertOk()
            ->assertSee('Home')
            ->assertSee('Features')
            ->assertSee('Business problem')
            ->assertSee('MarketLens solution')
            ->assertSee('User benefit')
            ->assertSee('Sales')
            ->assertSee('Payments')
            ->assertSee('Inventory')
            ->assertSee('Purchases')
            ->assertSee('Expenses')
            ->assertSee('Customer debts')
            ->assertSee('Supplier balances')
            ->assertSee('Staff accountability')
            ->assertSee('Cashier shifts')
            ->assertSee('Reporting')
            ->assertSee('Synchronization')
            ->assertSee('Remote monitoring')
            ->assertSee('Security')
            ->assertSee('Backup and recovery');
    }

    public function test_business_types_page_loads_profiles(): void
    {
        $response = $this->get('/business-types');

        $response
            ->assertOk()
            ->assertSee('Home')
            ->assertSee('Business types')
            ->assertSee('Common operational challenges')
            ->assertSee('Useful MarketLens modules')
            ->assertSee('Specialized features that may be added later')
            ->assertSee('Supermarket')
            ->assertSee('Provision business')
            ->assertSee('Wholesale distributor')
            ->assertSee('Pharmacy')
            ->assertSee('Building materials')
            ->assertSee('Electronics')
            ->assertSee('Cosmetics')
            ->assertSee('Agricultural products')
            ->assertSee('Spare parts')
            ->assertSee('Bookshop and stationery');
    }

    public function test_how_it_works_page_states_architecture_rules_in_business_language(): void
    {
        $response = $this->get('/how-it-works');

        $response
            ->assertOk()
            ->assertSee('Home')
            ->assertSee('How it works')
            ->assertSee('Business users are registered in the Business Server.')
            ->assertSee('Multiple Client Apps can connect through LAN or Wi-Fi.')
            ->assertSee('The Business Server owns the local database.')
            ->assertSee('Business operations continue offline.')
            ->assertSee('The Business Server synchronizes records to the Platform.')
            ->assertSee('The Monitoring App reads from the Platform.')
            ->assertSee('The Monitoring App does not connect directly to the business.');
    }

    public function test_versioned_api_returns_marketlens_header(): void
    {
        $response = $this->getJson('/api/v1/device/versions');

        $response
            ->assertOk()
            ->assertHeader('X-MarketLens-Api-Version', 'v1')
            ->assertJsonPath('success', true);
    }
}
