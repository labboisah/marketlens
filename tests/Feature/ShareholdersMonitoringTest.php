<?php

use Tests\TestCase;

class ShareholdersMonitoringTest extends TestCase
{
    public function test_shareholders_endpoint_requires_auth()
    {
        $response = $this->getJson('/api/v1/monitoring/shareholders');
        $response->assertStatus(401);
    }
}
