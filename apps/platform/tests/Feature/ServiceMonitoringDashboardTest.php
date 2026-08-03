<?php

use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class ServiceMonitoringDashboardTest extends TestCase
{
    public function test_dashboard_endpoint_requires_auth()
    {
        $response = $this->getJson('/api/v1/services');
        $response->assertStatus(401);
    }
}
