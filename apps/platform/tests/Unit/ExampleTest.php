<?php

namespace Tests\Unit;

use Tests\TestCase;

class PlatformFoundationTest extends TestCase
{
    public function test_platform_has_expected_api_version(): void
    {
        $this->assertSame('v1', config('marketlens.api_version'));
    }
}
