<?php

use Tests\TestCase;

class StaffEarningsMonitoringTest extends TestCase
{
    public function test_staff_earnings_endpoint_requires_auth()
    {
        $response = $this->getJson('/api/v1/staff-earnings');
        $response->assertStatus(401);
    }
}
