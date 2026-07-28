<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_to_platform_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('platform.dashboard'));
    }

    public function test_versioned_api_returns_marketlens_header(): void
    {
        $response = $this->postJson('/api/v1/auth/login');

        $response
            ->assertOk()
            ->assertHeader('X-MarketLens-Api-Version', 'v1')
            ->assertJsonPath('success', true);
    }
}
